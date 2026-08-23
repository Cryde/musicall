import {
  listedFolderId,
  NO_FOLDER_LISTED,
  TRASH_FOLDER_ID,
  virtualFolderSource
} from '../constants/folderSelection.js'
import { FILES_PAGE_SIZE } from './filePagination.js'

/**
 * What the Files panel asks the collection for, and what the rows it gets back have in common.
 *
 * Extracted from the store because the scoping rules are the interesting part: which of the sidebar
 * selection and the filter bar wins, and when a listing stops being one place in the tree.
 */

/**
 * A search is running, so the listing is a result set rather than a place.
 *
 * @param {{query: string}} filters
 * @returns {boolean}
 */
export function isSearchActive(filters) {
  return filters.query.trim() !== ''
}

/**
 * The folder every row on screen belongs to: null at the root, the id inside a folder, and
 * NO_FOLDER_LISTED when a row can have come from anywhere.
 *
 * A search is the third case whatever the sidebar has selected, because it covers the whole space: the
 * member who does not remember where they filed something is looking for it everywhere, so the listing
 * stops being the folder they happen to be standing in.
 *
 * @param {string|null} activeFolderId
 * @param {{query: string}} filters
 * @returns {string|null|symbol}
 */
export function listedFolderOfRows(activeFolderId, filters) {
  return isSearchActive(filters) ? NO_FOLDER_LISTED : listedFolderId(activeFolderId)
}

/**
 * The query string parameters for one page of the file collection.
 *
 * @param {string|null} activeFolderId
 * @param {{query: string, mime: ?string, tagId: ?string, source: ?string, sort: string, order: string}} filters
 * @param {number} page
 * @returns {object}
 */
export function fileListingParams(activeFolderId, filters, page) {
  // The trash ignores every filter, before they are even read. Its filter bar is hidden, so a search
  // or tag left over from browsing a folder would silently narrow the trash with no visible control to
  // clear it, and someone hunting for the file they just deleted would find it apparently missing.
  // It pages exactly like the live listing does: without that, a trashed file past the fiftieth is
  // unrestorable and app:band-space:purge eventually destroys it.
  if (activeFolderId === TRASH_FOLDER_ID) {
    return { archived: true, page, itemsPerPage: FILES_PAGE_SIZE }
  }

  const params = { page, itemsPerPage: FILES_PAGE_SIZE }
  const trimmed = filters.query.trim()
  if (trimmed) params.query = trimmed
  if (filters.mime) params.mime = filters.mime
  if (filters.tagId) params.tagId = filters.tagId
  if (filters.sort) params.sort = filters.sort
  if (filters.order) params.order = filters.order

  const virtualSource = virtualFolderSource(activeFolderId)
  if (virtualSource !== null) {
    // A virtual folder keeps its source even while searching: there the member is looking through one
    // set of attachments rather than through the folder tree, so the source is the subject of the
    // search and not a place to escape from.
    params.source = virtualSource
  } else if (activeFolderId && !isSearchActive(filters)) {
    // ROOT_FOLDER_ID rides along as a folder id, since the collection reads that reserved value as the
    // root of the tree. No folder_id at all is the whole space, which is what a search wants and what
    // « Tous les fichiers » selects.
    params.folderId = activeFolderId
  } else if (filters.source) {
    params.source = filters.source
  }

  return params
}
