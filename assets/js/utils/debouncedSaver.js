/**
 * The save scheduler behind every rich text surface: debounce an edit, flush whatever is still
 * waiting, and stop for good.
 *
 * `stop()` is the one that matters. When the server refuses a save because the document moved on
 * under the writer, the loop has to end right there: left running it would re-send the same refused
 * document every two seconds, and the flush on unmount would send it one final time. So nothing
 * schedules and nothing flushes after a stop, and there is no way back other than a fresh saver.
 *
 * Pure on purpose, with no editor and no component around it, so the timing rules are tested
 * without a browser. See debouncedSaver.test.js.
 *
 * @param {object}   options
 * @param {number}   options.delayMs
 * @param {function} options.onSave receives the last content handed to `schedule`
 */
export function createDebouncedSaver({ delayMs, onSave }) {
  let timeout = null
  let pendingContent = null
  let hasPendingContent = false
  let stopped = false
  let saving = false
  let queuedContent = null
  let hasQueuedContent = false

  function cancel() {
    if (timeout !== null) {
      clearTimeout(timeout)
      timeout = null
    }
    pendingContent = null
    hasPendingContent = false
  }

  /**
   * One save at a time, the latest content winning. A round trip slower than the debounce would
   * otherwise overlap two saves from the same writer, both naming the revision they started from,
   * and the second would come back refused as somebody else's edit when nobody else had touched it.
   */
  async function fire(content) {
    if (stopped) return

    if (saving) {
      queuedContent = content
      hasQueuedContent = true

      return
    }

    saving = true
    try {
      await onSave(content)
    } finally {
      saving = false
    }

    if (!stopped && hasQueuedContent) {
      const next = queuedContent
      queuedContent = null
      hasQueuedContent = false
      await fire(next)
    }
  }

  function schedule(content) {
    if (stopped) return

    cancel()
    pendingContent = content
    hasPendingContent = true
    timeout = setTimeout(() => {
      timeout = null
      const scheduled = pendingContent
      pendingContent = null
      hasPendingContent = false
      fire(scheduled)
    }, delayMs)
  }

  /** Writes anything still waiting on the debounce. Safe to call when nothing is pending. */
  function flush() {
    if (stopped || !hasPendingContent) return

    const pending = pendingContent
    cancel()
    fire(pending)
  }

  /** Permanent: drops what is waiting and refuses every later schedule and flush. */
  function stop() {
    stopped = true
    cancel()
    // A save queued behind one still in flight is dropped too: `fire` checks the flag before running
    // it, but leaving it set would keep a refused document alive in the closure for no reason.
    queuedContent = null
    hasQueuedContent = false
  }

  return { schedule, flush, cancel, stop }
}
