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
 * @param {string | undefined} [cookie] injectable, so this is testable without a DOM: the runner is
 *   plain `node --test` with no jsdom, and reading `document.cookie` there throws
 * @returns {object | null} the JWT payload, or null when the cookie is absent or unreadable
 */
export function readJwtPayload(cookie = Cookies.get(JWT_COOKIE)) {
  if (!cookie) {
    return null
  }

  try {
    return jwtDecode(cookie)
  } catch {
    return null
  }
}

/** Whether a token that has not expired is available right now. */
export function hasLiveToken(
  nowSeconds = Math.floor(Date.now() / 1000),
  payload = readJwtPayload()
) {
  return secondsUntilExpiry(payload, nowSeconds) > 0
}

let inFlight = null

/**
 * One refresh at a time, whoever asks for it.
 *
 * Sharing the promise is the whole point rather than a nicety: the refresh token is `single_use`, so
 * two calls in flight together means the second presents a token the first has already consumed and
 * is refused. The store and the 401 interceptor both come through here so that there is one promise
 * per tab and not one each.
 *
 * Two tabs are still two promises, because a cookie is the only thing they share. That race is
 * handled where it surfaces, by `hasLiveToken()`: a tab that loses it finds the cookies the winner
 * just wrote and simply carries on.
 *
 * @param {() => Promise<unknown>} [request] the call to make, injectable so the sharing can be tested
 */
export function refreshSession(request = () => securityApi.refreshToken()) {
  if (!inFlight) {
    inFlight = request().finally(() => {
      inFlight = null
    })
  }

  return inFlight
}
