/**
 * The real timers, for a module that takes its timers injected so that it can be tested.
 *
 * Wrapped, never referenced: a module calls them as `timers.set(...)`, which makes `timers` the `this`
 * of the call, and a browser refuses `setTimeout` on any `this` but the window. Firefox reports
 * « 'setTimeout' called on an object that does not implement interface Window », Chrome « Illegal
 * invocation ». Node does not check, so a test run against the bare references passes while the page
 * throws, which is how the notification stream shipped with a reconnect that could never be scheduled.
 *
 * @returns {{ set: (callback: () => void, delayMs: number) => unknown, clear: (handle: unknown) => void }}
 */
export function browserTimers() {
  return {
    set: (callback, delayMs) => setTimeout(callback, delayMs),
    clear: (handle) => clearTimeout(handle)
  }
}
