import { browserTimers } from './browserTimers.js'

/**
 * When a page counts as « here » (#1040): beating while shown, still here for `graceMs` once hidden,
 * gone after. The grace is what keeps a glance at another tab from sending « leaving » and a fresh
 * arrival to the whole band, and from spending the rate limit on it.
 *
 * @param {object} options
 * @param {{ start: Function, stop: Function, isRunning: boolean }} options.heartbeat
 * @param {() => void} options.leave
 * @param {number} options.graceMs
 * @param {{ set: Function, clear: Function }} [options.timers] injected for tests
 */
export function createPresenceSession({ heartbeat, leave, graceMs, timers = browserTimers() }) {
  let pendingLeave = null

  function cancelPendingLeave() {
    if (pendingLeave === null) return
    timers.clear(pendingLeave)
    pendingLeave = null
  }

  function leaveNow() {
    cancelPendingLeave()
    if (!heartbeat.isRunning) return
    heartbeat.stop()
    leave()
  }

  return {
    shown() {
      cancelPendingLeave()
      heartbeat.start()
    },
    hidden() {
      if (pendingLeave !== null || !heartbeat.isRunning) return
      pendingLeave = timers.set(() => {
        pendingLeave = null
        leaveNow()
      }, graceMs)
    },
    leaveNow
  }
}
