import { browserTimers } from './browserTimers.js'

/**
 * Runs `call` once at the end of a window that the first request opens, however many more requests
 * land inside it (#977).
 *
 * Trailing and fixed, not a debounce: a debounce restarts on every request, so a steady trickle of
 * signals would push the call back forever, while a fixed window always answers within `delayMs` of
 * the first one.
 *
 * Pure, with the timers injected, so it tests under `node --test` without a browser.
 *
 * @param {object} options
 * @param {number} options.delayMs
 * @param {() => unknown} options.call
 * @param {{ set: Function, clear: Function }} [options.timers] injected for tests
 */
export function createCoalescedCall({ delayMs, call, timers = browserTimers() }) {
  let handle = null

  function request() {
    if (handle !== null) {
      return
    }
    handle = timers.set(() => {
      handle = null
      call()
    }, delayMs)
  }

  function cancel() {
    if (handle !== null) {
      timers.clear(handle)
      handle = null
    }
  }

  return { request, cancel }
}
