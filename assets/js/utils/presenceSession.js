import { browserTimers } from './browserTimers.js'
import { createHeartbeat } from './heartbeat.js'

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

/**
 * A presence session for whichever conversation is open (#1246): a band's chat or a direct one. The
 * conversations page swaps one for the next without remounting, so switching leaves the old one before
 * beating the new, and `null` is « nothing to be present in » (a channel, which beats on its own).
 *
 * @param {object} options
 * @param {(target: string) => unknown} options.beat
 * @param {(target: string) => unknown} options.leave
 * @param {number} options.intervalMs
 * @param {number} options.graceMs
 * @param {{ set: Function, clear: Function }} [options.timers] injected for tests
 */
export function createTargetedPresence({
  beat,
  leave,
  intervalMs,
  graceMs,
  timers = browserTimers()
}) {
  let current = null
  const session = createPresenceSession({
    heartbeat: createHeartbeat({ intervalMs, beat: () => beat(current), timers }),
    // Read when it runs, not when built: a switch leaves before it moves `current` on.
    leave: () => leave(current),
    graceMs,
    timers
  })

  return {
    target(next, isVisible) {
      if ((next ?? null) === current) return
      session.leaveNow()
      current = next ?? null
      if (current !== null && isVisible) session.shown()
    },
    shown() {
      if (current !== null) session.shown()
    },
    hidden() {
      if (current !== null) session.hidden()
    },
    leaveNow: () => session.leaveNow()
  }
}
