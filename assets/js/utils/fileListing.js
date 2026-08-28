import { fileSourceLabel } from '../constants/fileSources.js'
import {
  isVirtualFolderId,
  listedFolderId,
  NO_FOLDER_LISTED,
  ROOT_FOLDER_ID,
  TRASH_FOLDER_ID,
  virtualFolderId,
  virtualFolderSource
} from '../constants/folderSelection.js'
import { FILES_PAGE_SIZE } from './filePagination.js'

/**
 * What the Files panel asks the collection for, and what the rows it gets back have in common.
 *
 * Extracted from the store because the scoping rules are the interesting part: which of the sidebar
 * selection and the filter bar wins, and when a listing stops being one place in the tree.
 *
 * The same rules answer where a row can be moved to, so what the root holds is written here once
 * rather than once per surface offering it as a destination, which is how the move dialog came to
 * offer a destination the root does not show.
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

/**
 * How many files a folder row holds, or null when it holds none: an empty folder says nothing rather
 * than « 0 fichier », because the count leaves out its subfolders and a zero would read as « rien
 * là-dedans » on a folder whose subfolders are full.
 *
 * @param {number|null|undefined} fileCount
 * @returns {string|null}
 */
export function folderFileCountLabel(fileCount) {
  if (!fileCount || fileCount < 1) return null

  return fileCount > 1 ? `${fileCount} fichiers` : `${fileCount} fichier`
}

/**
 * Whether a folder is still somewhere in the tree.
 *
 * Asked after a folder was deleted, because a delete takes a whole subtree with it depending on the
 * strategy: the panel can be standing inside a folder that no longer exists without being inside the
 * one that was deleted. Reading it back off the refreshed tree answers that without the client having
 * to reimplement which strategy removes what.
 *
 * @param {Array} tree nested folders, each with an optional `children`
 * @param {string|null} folderId
 * @returns {boolean}
 */
export function treeHoldsFolder(tree, folderId) {
  if (!Array.isArray(tree) || folderId === null) return false

  return tree.some((node) => node.id === folderId || treeHoldsFolder(node.children ?? [], folderId))
}

/**
 * The path down to a folder, root first, in the shape the API spells `folder_path`.
 *
 * Read off the tree already in the store, so a row's path can be corrected after a move without asking
 * the server for it again. Empty for the root and for a folder the tree does not hold.
 *
 * @param {Array} tree
 * @param {string|null} folderId
 * @returns {Array<{id: string, name: string}>}
 */
export function folderPathOf(tree, folderId) {
  if (!Array.isArray(tree) || folderId === null || folderId === undefined) return []

  for (const node of tree) {
    if (node.id === folderId) return [{ id: node.id, name: node.name }]

    const below = folderPathOf(node.children ?? [], folderId)
    if (below.length > 0) return [{ id: node.id, name: node.name }, ...below]
  }

  return []
}

/**
 * The virtual folders a file's attachments put it in, one per distinct source, named the way the
 * sidebar names them and falling back to the source's own label before the tree has loaded. Empty for
 * a file nothing is attached to.
 *
 * @param {object} file
 * @param {Array<{source: string, name: string}>} virtualFolders
 * @returns {Array<{label: string, folderId: string}>}
 */
export function fileVirtualFolders(file, virtualFolders = []) {
  const sourceTypes = [...new Set((file?.attachments ?? []).map((att) => att.source_type))]

  return sourceTypes.map((sourceType) => ({
    label:
      virtualFolders.find((folder) => folder.source === sourceType)?.name ??
      fileSourceLabel(sourceType),
    folderId: virtualFolderId(sourceType)
  }))
}

/**
 * Whether the root of the tree is a place this file can be in.
 *
 * The root is not every file without a folder: it is the files with no folder *and* no attachment,
 * because an attachment with no folder is already listed in its virtual folder. So a file with
 * attachments has no place at the root, and offering it as a destination hides the file from the tree.
 *
 * @param {object} file  anything carrying the API's `attachments`, a row or a drag source
 * @returns {boolean}
 */
export function canFileSitAtRoot(file) {
  return (file?.attachments ?? []).length === 0
}

/**
 * Why the root is refused as a destination, naming where the file is listed instead, or null when it
 * is not refused. Reads under the destination field rather than in a toast after the move, so the rule
 * is visible before the member commits to it.
 *
 * @param {object} file
 * @param {Array<{source: string, name: string}>} virtualFolders
 * @returns {?string}
 */
export function rootDestinationRefusal(file, virtualFolders = []) {
  if (canFileSitAtRoot(file)) return null

  const names = fileVirtualFolders(file, virtualFolders).map((folder) => folder.label)
  const listedIn =
    names.length > 1 ? `${names.slice(0, -1).join(', ')} et ${names.at(-1)}` : names[0]

  return `Ce fichier est listé dans ${listedIn} : la racine ne liste que les fichiers attachés à aucune ressource.`
}

/**
 * Where a file row says it lives, and where clicking that says takes the member.
 *
 * A file in a folder names its path. A file in no folder is only at the root if nothing is attached to
 * it: the root excludes attachments, so sending an attached file's reader there would land them in a
 * listing the file is missing from. Its place is the virtual folder grouping its source.
 *
 * @param {object} file
 * @param {Array<{source: string, name: string}>} virtualFolders
 * @returns {{label: string, folderId: string}}
 */
export function fileLocation(file, virtualFolders = []) {
  const path = file.folder_path ?? []
  if (path.length > 0) {
    return {
      label: path.map((segment) => segment.name).join(' / '),
      folderId: path[path.length - 1].id
    }
  }

  return (
    fileVirtualFolders(file, virtualFolders)[0] ?? { label: 'Racine', folderId: ROOT_FOLDER_ID }
  )
}

/**
 * Whether a file that has just been uploaded belongs in the listing on screen, so its row can be shown
 * straight away instead of waiting for a refetch.
 *
 * It has to be the same place, and the listing has to be a place: a virtual folder holds attachments,
 * which an upload is not. Anything narrowing the listing further, a search, a tag, a type, makes this
 * undecidable here, because only the server knows what its own query matches, and a row that does not
 * match is a row the next fetch takes away again.
 *
 * @param {string|null} activeFolderId
 * @param {{query: string, tagId: ?string, mime: ?string}} filters
 * @param {string|null} uploadedFolderId
 * @returns {boolean}
 */
export function uploadBelongsInListing(activeFolderId, filters, uploadedFolderId) {
  if (isSearchActive(filters) || filters.tagId || filters.mime) return false
  // No selection at all is the flat listing of the whole space, which holds every file in it.
  if (activeFolderId === null) return true
  if (isVirtualFolderId(activeFolderId) || activeFolderId === TRASH_FOLDER_ID) return false

  return listedFolderId(activeFolderId) === (uploadedFolderId ?? null)
}
