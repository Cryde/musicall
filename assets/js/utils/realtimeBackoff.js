/**
 * How long to wait before reopening a dropped realtime connection.
 *
 * `EventSource` reconnects on its own, which is fine for one browser and wrong for all of them at
 * once: every deploy restarts FrankenPHP, every open stream drops in the same instant, and the
 * built-in retry brings them all back together. The jitter is the point of this function, not the
 * growth.
 *
 * Exponential from `BASE_DELAY_MS`, capped at `MAX_BASE_DELAY_MS`, then spread across a window
 * either side of that value so two clients that failed together do not return together. The spread
 * is applied after the cap, so the delay actually returned reaches 1.5 times it.
 */

const BASE_DELAY_MS = 1_000
const MAX_BASE_DELAY_MS = 30_000
/** Fraction of the delay the jitter may add or remove. */
const JITTER_RATIO = 0.5

/**
 * @param {number} attempt zero for the first retry after a working connection
 * @param {() => number} random injectable so the spread can be asserted rather than sampled
 * @returns {number} milliseconds to wait
 */
export function reconnectDelay(attempt, random = Math.random) {
  const safeAttempt = Number.isFinite(attempt) && attempt > 0 ? Math.floor(attempt) : 0
  const exponential = Math.min(BASE_DELAY_MS * 2 ** safeAttempt, MAX_BASE_DELAY_MS)
  const spread = exponential * JITTER_RATIO

  return Math.round(exponential - spread + random() * spread * 2)
}

/** The ceiling before jitter. What the function returns goes half again as high. */
export const RECONNECT_MAX_BASE_DELAY_MS = MAX_BASE_DELAY_MS

/** What the function will actually never exceed. */
export const RECONNECT_MAX_DELAY_MS = MAX_BASE_DELAY_MS * (1 + JITTER_RATIO)
