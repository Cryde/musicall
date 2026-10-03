/**
 * A tab opened before a deploy still asks for the chunks of the build it started with, and each
 * release has its own `public/build`, so those are gone (#1105). Loading the page again picks up
 * the new build. Once only: if it still fails right after, the problem is not a stale build, and
 * reloading again would loop.
 */

export const STALE_BUILD_RELOAD_KEY = 'musicall:stale-build-reload'

/** How long after a reload a second failure is taken as « reloading did not help ». */
export const RELOAD_GUARD_MS = 10_000

// Chrome, Firefox and Safari word a failed dynamic import differently, and Safari words it a third
// way when the missing file is answered with an HTML page instead of a 404.
const CHUNK_LOAD_ERROR =
  /Failed to fetch dynamically imported module|error loading dynamically imported module|Importing a module script failed|is not a valid JavaScript MIME type|Unable to preload CSS/i

export function isChunkLoadError(error) {
  return CHUNK_LOAD_ERROR.test(String(error?.message ?? error ?? ''))
}

/**
 * Reloads unless a reload already happened within the guard window. Returns whether it did, so the
 * caller lets the error through otherwise.
 *
 * @param {{ storage: Storage, now: () => number, load: (url?: string) => void, url?: string }} options
 */
export function reloadOnceForStaleBuild({ storage, now, load, url }) {
  let last = 0
  try {
    last = Number(storage.getItem(STALE_BUILD_RELOAD_KEY)) || 0
  } catch {
    // Storage blocked: reloading without the guard could loop, so do not.
    return false
  }
  if (now() - last < RELOAD_GUARD_MS) return false

  try {
    storage.setItem(STALE_BUILD_RELOAD_KEY, String(now()))
  } catch {
    return false
  }
  load(url)

  return true
}
