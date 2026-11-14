import { browserTimers } from './browserTimers.js'
import {
  AUTH_STEP,
  authStep,
  classifyRefreshFailure,
  forgetSignedIn,
  hasLiveToken,
  REFRESH_FAILURE,
  readJwtPayload,
  refreshSession,
  rememberSignedIn,
  tokenNeedsRenewing,
  wasSignedIn
} from './sessionRefresh.js'

/**
 * How often the timer looks at the token.
 *
 * **Strictly shorter than REFRESH_BUFFER_SECONDS.** Both used to be 300, so the checks landed on
 * 600, exactly 300, then 0 seconds left, and the buffer never fired once (#1008).
 */
export const CHECK_INTERVAL_MS = 60 * 1000

/** Stops a refresh that keeps answering without a usable token from recursing. */
const MAX_RENEWALS = 2

/**
 * Who is signed in, and keeping them so: the security store's session logic (#1008, #1022), apart
 * from Pinia, the router and the API so that `node --test` can drive it. The store's own state goes
 * in through `session`.
 *
 * A session ends only when the server refuses the refresh token. A renewal that gets no answer keeps
 * the timer retrying, for a member signed in and one who arrived offline alike.
 *
 * @param {object} options
 * @param {{
 *   isSignedIn: () => boolean,
 *   signedIn: (payload: object) => void,
 *   signedOut: () => void,
 *   expired: () => void
 * }} options.session the store's state
 * @param {() => Promise<unknown>} [options.refresh]
 * @param {() => object | null} [options.readPayload]
 * @param {Storage | null} [options.storage] where the signed in flag lives, localStorage by default
 * @param {() => number} [options.now] seconds since the epoch
 * @param {{ set: Function, clear: Function }} [options.timers]
 */
export function createSessionKeeper({
  session,
  refresh = () => refreshSession(),
  readPayload = () => readJwtPayload(),
  storage,
  now = () => Math.floor(Date.now() / 1000),
  timers = browserTimers()
}) {
  let running = false
  let handle = null
  // Bumped by end(), so a renewal still in flight when the session ends does not sign back in.
  let endings = 0

  /** Works out who is signed in, renewing the token when it is missing or nearly out. */
  async function check(renewals = 0) {
    const payload = readPayload()
    const step = authStep(payload, now(), wasSignedIn(storage))

    if (step === AUTH_STEP.USE_TOKEN) {
      return signIn(payload)
    }
    if (step === AUTH_STEP.SIGNED_OUT || renewals >= MAX_RENEWALS) {
      return signOut()
    }

    const endingsBefore = endings
    try {
      await refresh()
    } catch (error) {
      return endings === endingsBefore ? recover(error) : false
    }

    return endings === endingsBefore ? check(renewals + 1) : false
  }

  function recover(error) {
    const payload = readPayload()

    switch (classifyRefreshFailure(error, hasLiveToken(now(), payload))) {
      case REFRESH_FAILURE.COVERED:
        return signIn(payload)
      case REFRESH_FAILURE.REFUSED:
        expire()

        return false
      default:
        // No verdict, so nothing is cleared: a member signed in stays so, one arriving offline is
        // shown signed out for now, and the timer tries again either way.
        if (!session.isSignedIn()) {
          session.signedOut()
        }
        startTimer()

        return session.isSignedIn()
    }
  }

  function signIn(payload) {
    // Here rather than in the password form, which a Google or Apple account never goes through.
    rememberSignedIn(storage)
    session.signedIn(payload)
    startTimer()

    return true
  }

  function signOut() {
    stopTimer()
    session.signedOut()

    return false
  }

  /** The refresh token was refused: the session is over and cannot be renewed. */
  function expire() {
    end()
    session.signedOut()
    session.expired()
  }

  /** Forgets this browser was signed in, for a logout or a refused session. */
  function end() {
    endings += 1
    forgetSignedIn(storage)
    stopTimer()
  }

  function startTimer() {
    if (running) {
      return
    }
    running = true
    handle = timers.set(tick, CHECK_INTERVAL_MS)
  }

  function stopTimer() {
    running = false
    if (handle !== null) {
      timers.clear(handle)
      handle = null
    }
  }

  async function tick() {
    handle = null
    try {
      if (!session.isSignedIn() && !wasSignedIn(storage)) {
        stopTimer()

        return
      }

      if (tokenNeedsRenewing(readPayload(), now())) {
        await check()
      }
    } finally {
      // Even after a throw. check() may have stopped the timer, or restarted it with a tick scheduled.
      if (running && handle === null) {
        handle = timers.set(tick, CHECK_INTERVAL_MS)
      }
    }
  }

  return { check, expire, end }
}
