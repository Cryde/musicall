import { browserTimers } from './browserTimers.js'

/**
 * Calls `beat` now and then every `intervalMs` until stopped (#1040). Starting twice is one heartbeat,
 * so a page shown again after being hidden cannot stack a second one.
 *
 * @param {object} options
 * @param {number} options.intervalMs
 * @param {() => unknown} options.beat
 * @param {{ set: Function, clear: Function }} [options.timers] injected for tests
 */
export function createHeartbeat({ intervalMs, beat, timers = browserTimers() }) {
  let handle = null

  function schedule() {
    handle = timers.set(() => {
      beat()
      schedule()
    }, intervalMs)
  }

  return {
    start() {
      if (handle !== null) return
      beat()
      schedule()
    },
    stop() {
      if (handle === null) return
      timers.clear(handle)
      handle = null
    },
    get isRunning() {
      return handle !== null
    }
  }
}
