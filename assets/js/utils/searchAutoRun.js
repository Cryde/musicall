import { browserTimers } from './browserTimers.js'

/**
 * Filters that search on their own (#1075): a change refreshes the results after `refreshDelay` of
 * quiet, and the search is recorded only once its results have stood for `settleDelay`, so the search
 * log keeps the searches people ran, not each step on the way to one. Pure, with the timers injected.
 *
 * @param {object} options
 * @param {() => unknown} options.refresh shows the results without recording them; may return a promise
 * @param {() => unknown} options.record records the search on screen
 * @param {number} [options.refreshDelay]
 * @param {number} [options.settleDelay]
 * @param {{ set: Function, clear: Function }} [options.timers] injected for tests
 */
export function createSearchAutoRun({
  refresh,
  record,
  refreshDelay = 500,
  settleDelay = 3000,
  timers = browserTimers()
}) {
  let refreshHandle = null
  let settleHandle = null
  let inFlight = null
  let unrecorded = false
  // Bumped by every change: an answer or a record for an older search is dropped.
  let generation = 0

  function cancel() {
    generation++
    if (refreshHandle !== null) timers.clear(refreshHandle)
    if (settleHandle !== null) timers.clear(settleHandle)
    refreshHandle = null
    settleHandle = null
    inFlight = null
    unrecorded = false
  }

  function runRefresh() {
    refreshHandle = null
    const run = generation
    let result
    try {
      result = refresh()
    } catch (error) {
      result = Promise.reject(error)
    }
    // A failed refresh shows nothing new, so there is nothing to record.
    inFlight = Promise.resolve(result).then(
      () => {
        if (run !== generation) return
        inFlight = null
        unrecorded = true
        settleHandle = timers.set(runRecord, settleDelay)
      },
      () => {
        if (run === generation) inFlight = null
      }
    )
  }

  function runRecord() {
    settleHandle = null
    if (!unrecorded) return
    unrecorded = false
    record()
  }

  function changed() {
    cancel()
    refreshHandle = timers.set(runRefresh, refreshDelay)
  }

  /** Records at once, for an action showing the member is reading these results. */
  async function settleNow() {
    const run = generation
    if (refreshHandle !== null) {
      timers.clear(refreshHandle)
      runRefresh()
    }
    if (inFlight) await inFlight
    if (run !== generation) return
    if (settleHandle !== null) {
      timers.clear(settleHandle)
      settleHandle = null
    }
    runRecord()
  }

  return { changed, settleNow, cancel }
}
