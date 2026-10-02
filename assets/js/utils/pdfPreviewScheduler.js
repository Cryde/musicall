/**
 * When the tech rider preview renders its PDF (#1092). Each render is a Gotenberg call that takes a
 * second or two, so it waits for edits to settle, runs only between start and stop (while the
 * preview is open), and never overlaps another one: a change arriving mid render is picked up next.
 *
 * @param {object}   options
 * @param {function} options.render   () => Promise, one render of the current rider
 * @param {number}   [options.delayMs] how long edits must settle before a render
 * @param {function} [options.onBusy]  (boolean) => void, true from a change until its PDF is shown
 */
export function createPdfPreviewScheduler({ render, delayMs = 3000, onBusy = () => {} }) {
  let isRunning = false
  let isStale = false
  let isRendering = false
  let timer = null
  let wasBusy = false

  // Busy covers the wait as well as the render: the PDF on screen is out of date from the edit on.
  function reportBusy() {
    const isBusy = isRendering || timer !== null
    if (isBusy === wasBusy) return
    wasBusy = isBusy
    onBusy(isBusy)
  }

  function cancelTimer() {
    clearTimeout(timer)
    timer = null
  }

  async function run() {
    cancelTimer()
    if (isRendering) return
    isRendering = true
    isStale = false
    reportBusy()
    try {
      await render()
    } catch {
      // The caller shows the failure. A change made meanwhile still renders below, so a server that
      // keeps failing is retried once per change, never in a loop of its own.
    } finally {
      isRendering = false
    }
    // Edits made while rendering are not in this PDF. Their own wait may already be over.
    if (isStale && isRunning && timer === null) {
      run()
      return
    }
    reportBusy()
  }

  function changed() {
    isStale = true
    if (!isRunning) return
    cancelTimer()
    timer = setTimeout(() => {
      timer = null
      run()
    }, delayMs)
    reportBusy()
  }

  /** Opening shows the current rider at once. */
  function start() {
    isRunning = true
    run()
  }

  function stop() {
    isRunning = false
    cancelTimer()
    reportBusy()
  }

  /** A retry after a failed render. */
  function refresh() {
    isStale = true
    if (isRunning) run()
  }

  return { changed, start, stop, refresh }
}
