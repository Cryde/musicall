import * as Cookies from 'es-cookie'
import { jwtDecode } from 'jwt-decode'
import securityApi from '../api/user/security.js'

/**
 * Keeping a signed-in session alive (#1008).
 *
 * The JWT lasts an hour and its cookie is deleted by the browser the moment it expires, while the
 * refresh token behind it is good for thirty days. Everything here exists so that the gap between
 * those two numbers is invisible: a request that comes back 401 because the token aged out is a
 * token to renew, not a session to end.
 *
 * The decisions are separated from the fetching on purpose. The decisions are pure and tested; the
 * one impure thing, the shared in-flight promise, is the part that has to be shared to be correct.
 */

/**
 * How long before expiry the proactive timer starts renewing.
 *
 * **This must stay strictly larger than the interval the timer runs on**, or a check can step over
 * the whole window: with both set to 300 the checks landed on 600, then exactly 300, then 0, and
 * `300 < 300` is false, so the buffer never fired once and only expiry itself ever recovered.
 */
export const REFRESH_BUFFER_SECONDS = 300

/** The split cookie holding the header and payload. The signature half is httpOnly and unreadable. */
const JWT_COOKIE = 'jwt_hp'

/**
 * Endpoints where a 401 is the answer rather than a stale token: signing in with a wrong password,
 * and the refresh call itself, which must never be able to trigger another refresh.
 */
export function isAuthEndpoint(url) {
  const target = url ?? ''

  return target.includes('/api/login') || target.includes('/api/token/refresh')
}

/**
 * @param {{exp?: number} | null} payload a decoded JWT
 * @param {number} nowSeconds the current time, in seconds since the epoch
 * @returns {number} seconds left, 0 when there is no usable expiry
 */
export function secondsUntilExpiry(payload, nowSeconds) {
  const exp = payload?.exp

  return typeof exp === 'number' ? Math.max(0, exp - nowSeconds) : 0
}

/**
 * Renew a token that still works but is about to stop, never one that has already gone: expiry is
 * the reactive path's job, and refreshing from here as well would mean two callers racing to consume
 * a single use token.
 */
export function needsProactiveRefresh(remainingSeconds, buffer = REFRESH_BUFFER_SECONDS) {
  return remainingSeconds > 0 && remainingSeconds <= buffer
}

/**
 * Whether a token is too near its end to start a page on. Not expiry alone: one with two minutes left
 * is renewed rather than left to be refused halfway through a navigation.
 */
export function tokenNeedsRenewing(payload, nowSeconds) {
  const remaining = secondsUntilExpiry(payload, nowSeconds)

  return remaining === 0 || needsProactiveRefresh(remaining)
}

/** What the store does with the token it finds. */
export const AUTH_STEP = Object.freeze({
  USE_TOKEN: 'use_token',
  RENEW: 'renew',
  SIGNED_OUT: 'signed_out'
})

/**
 * A token that is merely old is renewed whatever storage says. Only when there is nothing to read
 * does it take this browser having been signed in, which is the ordinary state an hour after the
 * last visit: the browser deletes the cookie the moment the token expires.
 *
 * @param {object | null} payload the JWT payload, null when the cookie is gone
 * @param {number} nowSeconds
 * @param {boolean} signedInBefore what `wasSignedIn()` says
 * @returns {string} one of AUTH_STEP
 */
export function authStep(payload, nowSeconds, signedInBefore) {
  if (!tokenNeedsRenewing(payload, nowSeconds)) {
    return AUTH_STEP.USE_TOKEN
  }

  return payload !== null || signedInBefore ? AUTH_STEP.RENEW : AUTH_STEP.SIGNED_OUT
}

/**
 * @param {string | undefined} [cookie] injectable, so this is testable without a DOM: the runner is
 *   plain `node --test` with no jsdom, and reading `document.cookie` there throws
 * @returns {object | null} the JWT payload, or null when the cookie is absent or unreadable
 */
export function readJwtPayload(cookie = readJwtCookie()) {
  if (!cookie) {
    return null
  }

  try {
    return jwtDecode(cookie)
  } catch {
    return null
  }
}

/** The raw readable half of the JWT, undefined when the browser has deleted it. */
function readJwtCookie() {
  return Cookies.get(JWT_COOKIE)
}

/** Whether a token that has not expired is available right now. */
export function hasLiveToken(
  nowSeconds = Math.floor(Date.now() / 1000),
  payload = readJwtPayload()
) {
  return secondsUntilExpiry(payload, nowSeconds) > 0
}

/** Every tab of the site queues on this name, so only one of them renews at a time (#1022). */
const REFRESH_LOCK = 'musicall-session-refresh'

/**
 * Runs a task holding the cross-tab refresh lock, or straight away where Web Locks are missing (an
 * older browser, a page served over plain http).
 *
 * @param {{request: Function} | undefined} [locks] `navigator.locks`, injectable for the tests
 * @returns {(task: () => Promise<unknown>) => Promise<unknown>}
 */
export function lockAcrossTabs(locks = globalThis.navigator?.locks) {
  return locks ? (task) => locks.request(REFRESH_LOCK, task) : (task) => task()
}

let inFlight = null

/**
 * One refresh at a time, whoever asks for it, in this tab and across tabs.
 *
 * The refresh token is `single_use`, so two calls in flight together means the second presents a
 * token the first has already consumed and is refused. Within a tab, the store and the 401
 * interceptor share one promise. Across tabs, a browser restoring several at once used to have them
 * all renew together, and a loser whose refusal arrived before the winner's cookie was signed out.
 * Now they queue on a lock, and one that finds a new live cookie once its turn comes knows another
 * tab renewed while it waited, so it sends nothing. An unchanged cookie is renewed even if it looks
 * live: it may be half a pair, `jwt_s` dying with the browser while `jwt_hp` outlives it.
 *
 * @param {() => Promise<unknown>} [request] the call to make
 * @param {{lock?: Function, readCookie?: () => string | undefined}} [seams] injectable for the tests
 */
export function refreshSession(
  request = () => securityApi.refreshToken(),
  { lock = lockAcrossTabs(), readCookie = readJwtCookie } = {}
) {
  if (!inFlight) {
    const cookieBefore = readCookie()

    inFlight = lock(async () => {
      const cookieNow = readCookie()
      if (cookieNow !== cookieBefore && hasLiveToken(undefined, readJwtPayload(cookieNow))) {
        return
      }

      return request()
    }).finally(() => {
      inFlight = null
    })
  }

  return inFlight
}

/** How a failed refresh is read: whether the session is over, and if not, what to do next. */
export const REFRESH_FAILURE = Object.freeze({
  /** A usable token is there all the same: another tab renewed first, or ours has minutes left. */
  COVERED: 'covered',
  /** The server refused the refresh token, so the session really is over. */
  REFUSED: 'refused',
  /** No verdict on the session: offline, a timeout, a 5xx. Worth trying again later (#1022). */
  UNANSWERED: 'unanswered'
})

/**
 * Only a 401 from the refresh endpoint ends a session. Anything else is a request that never got a
 * verdict, and treating it as one used to end a thirty day session over a network blip (#1022).
 *
 * @param {{response?: {status?: number}} | undefined} error what the refresh call rejected with
 * @param {boolean} tokenIsLive whether a token that has not expired is available right now
 * @returns {string} one of REFRESH_FAILURE
 */
export function classifyRefreshFailure(error, tokenIsLive) {
  if (tokenIsLive) {
    return REFRESH_FAILURE.COVERED
  }

  return error?.response?.status === 401 ? REFRESH_FAILURE.REFUSED : REFRESH_FAILURE.UNANSWERED
}

/**
 * Whether this browser was signed in, which is all that authorises a renewal once the browser has
 * deleted the expired JWT cookie. Written on every sign in, not only the password one, since a Google
 * or Apple account never goes through it (#1022).
 */
const SIGNED_IN_FLAG = 'was_logged_in'

/** Null when storage is blocked, where merely reading `localStorage` throws. */
function openSignedInStorage() {
  try {
    return globalThis.localStorage ?? null
  } catch {
    return null
  }
}

export function rememberSignedIn(storage = openSignedInStorage()) {
  try {
    storage?.setItem(SIGNED_IN_FLAG, 'true')
  } catch {
    // Blocked storage: this tab still works, only a renewal after a restart is lost.
  }
}

export function wasSignedIn(storage = openSignedInStorage()) {
  try {
    return storage?.getItem(SIGNED_IN_FLAG) === 'true'
  } catch {
    return false
  }
}

export function forgetSignedIn(storage = openSignedInStorage()) {
  try {
    storage?.removeItem(SIGNED_IN_FLAG)
  } catch {
    // Blocked storage holds no flag.
  }
}
