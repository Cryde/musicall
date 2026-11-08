/**
 * Wraps `fetch` so that only the newest call's outcome counts, and an older call settles with it.
 *
 * Dropping a stale response is not enough: whoever awaited the older call would carry on as if the
 * data had arrived. The band layout did exactly that when a locale switch remounted it mid-load, and
 * declared the space it was opening "not found" against a list that was still empty.
 *
 * @template T
 * @param {(...args: unknown[]) => Promise<T>} fetch
 * @returns {(...args: unknown[]) => Promise<T>}
 */
export function latestOnly(fetch) {
  let latest = null

  return (...args) => {
    const call = fetch(...args).then(
      (value) => (call === latest ? value : latest),
      (error) => {
        if (call === latest) throw error
        return latest
      }
    )
    latest = call
    return call
  }
}
