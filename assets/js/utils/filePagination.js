/**
 * Page bookkeeping for the band space file list and its trash.
 *
 * The collection is offset paginated server side and the list mutates under the user while later
 * pages are already on screen: files get uploaded, deleted, restored from the trash, or moved out
 * of the folder being browsed. Offset paging normally loses rows when that happens, because the
 * server window slides left by one for every row that disappears while the client keeps counting
 * pages from a number those mutations never touched.
 *
 * The next page is therefore derived from how many rows are actually loaded, and pages are merged
 * by id rather than blindly concatenated. Delete one of the 50 loaded rows and the next request
 * goes back to page 1: merging drops the 49 rows already held and keeps the one that slid into the
 * window, instead of stepping straight over it. That is what stops a file in the trash becoming
 * unreachable, which matters because app:band-space:purge destroys what the user cannot restore.
 *
 * That covers rows the list itself removed. It does NOT cover a row another member inserts behind
 * the window, which lands wherever its creation date falls: the loaded count then stops growing, so
 * the derived page stops moving and the same offset is requested from then on. The store watches
 * for that exact signature, a page bringing nothing new while the server still reports more, and
 * rebuilds from the first page rather than trusting these maths to converge on their own.
 */

/**
 * Rows per request. Sent explicitly as itemsPerPage, so the server takes it from here rather than
 * the two sides having to agree on a number neither can see. Capped server side at 200.
 */
export const FILES_PAGE_SIZE = 50

/**
 * The page to request next, derived from the rows already held rather than from a counter.
 *
 * @param {number} loadedCount rows currently in the list
 * @param {number} pageSize
 * @returns {number} 1 based page number
 */
export function nextPageToLoad(loadedCount, pageSize = FILES_PAGE_SIZE) {
  if (pageSize <= 0) {
    return 1
  }

  return Math.floor(Math.max(0, loadedCount) / pageSize) + 1
}

/**
 * Whether the server holds rows the list has not loaded yet.
 *
 * @param {number} loadedCount
 * @param {number} total totalItems reported by the collection
 * @returns {boolean}
 */
export function hasMoreToLoad(loadedCount, total) {
  return loadedCount < total
}

/**
 * Merge a freshly fetched page into the loaded rows: known ids are refreshed in place, unknown ids
 * are appended. Returns a new array and never mutates its inputs.
 *
 * Merging rather than concatenating is what makes a repeated page harmless, and repeated pages are
 * the price of the self correcting page maths above.
 *
 * @param {Array<{id: string}>} loaded
 * @param {Array<{id: string}>} incoming
 * @returns {Array<{id: string}>}
 */
export function mergePage(loaded, incoming) {
  const merged = [...loaded]
  const indexById = new Map(merged.map((item, index) => [item.id, index]))

  for (const item of incoming) {
    const knownIndex = indexById.get(item.id)
    if (knownIndex === undefined) {
      indexById.set(item.id, merged.length)
      merged.push(item)
    } else {
      merged[knownIndex] = item
    }
  }

  return merged
}

/**
 * A stable fingerprint of the request parameters, ignoring the page itself. Two calls describing
 * the same folder, filters and sort share a key whatever order the object was built in.
 *
 * The loaded pages belong to exactly one key. When the user changes a filter while a further page
 * is in flight, the key moves and the late response can be recognised as describing a list that is
 * no longer on screen, rather than being appended to rows it has nothing to do with.
 *
 * @param {Record<string, unknown>} params
 * @returns {string}
 */
export function queryKeyOf(params) {
  const entries = Object.keys(params)
    .filter((key) => key !== 'page')
    .sort()
    .map((key) => [key, params[key] ?? null])

  return JSON.stringify(entries)
}

/**
 * How many files exist, and how many of them are on screen. Rendered above the list so a truncated
 * view can never pass for the whole of it.
 *
 * @param {number} loadedCount
 * @param {number} total
 * @returns {string} empty when there is nothing to announce
 */
export function fileCountLabel(loadedCount, total) {
  if (total <= 0) {
    return ''
  }

  if (loadedCount >= total) {
    return total > 1 ? `${total} fichiers` : `${total} fichier`
  }

  return loadedCount > 1
    ? `${loadedCount} fichiers affichés sur ${total}`
    : `${loadedCount} fichier affiché sur ${total}`
}
