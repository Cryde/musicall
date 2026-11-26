/**
 * What the Files sidebar can have selected, and which folder each selection means.
 *
 * A real folder is selected by its uuid. The other rows are not folders, so they carry reserved ids
 * instead: the root of the tree, the trash, the virtual source folders and the songs under « Titres ».
 * No selection at all is the flat listing of the whole space, which is a view rather than a place.
 */

/**
 * The root of the folder tree. Mirrors BandSpaceFileCollectionProvider::ROOT_FOLDER_ID: the file
 * collection reads `folder_id=root` as the files at the root, where no `folder_id` still means the
 * whole space.
 */
export const ROOT_FOLDER_ID = 'root'

/** The trash, selected like a folder so it reuses the whole selection and fetch path. */
export const TRASH_FOLDER_ID = 'trash'

const VIRTUAL_PREFIX = 'virtual:'

/** Between a virtual folder's source and the one source of that kind a node under it lists. */
const SOURCE_ID_SEPARATOR = ':'

const SONG_SOURCE = 'song'

/** What listedFolderId returns when the selection is not a place in the tree. */
export const NO_FOLDER_LISTED = Symbol('no folder listed')

/**
 * The folder the panel is inside, spelled the way a file spells its own: null at the root, the uuid
 * inside a folder. The root is null rather than its reserved id because everything downstream already
 * spells the root that way, from a file's folder_id to the parent_id of a new folder.
 *
 * NO_FOLDER_LISTED for the three selections that are not a place: the flat listing of the whole space,
 * a virtual source folder, and the trash. Those list files from anywhere, so a row cannot be compared
 * against a folder and there are no subfolders to show inside them.
 *
 * @param {string|null} activeFolderId
 * @returns {string|null|symbol}
 */
export function listedFolderId(activeFolderId) {
  if (activeFolderId === ROOT_FOLDER_ID) return null
  if (typeof activeFolderId !== 'string') return NO_FOLDER_LISTED
  if (activeFolderId === TRASH_FOLDER_ID || isVirtualFolderId(activeFolderId)) {
    return NO_FOLDER_LISTED
  }

  return activeFolderId
}

/**
 * A virtual source folder groups files by what they are attached to, so it holds no files of its own
 * and nothing can be created in it.
 *
 * @param {string|null} folderId
 * @returns {boolean}
 */
export function isVirtualFolderId(folderId) {
  return typeof folderId === 'string' && folderId.startsWith(VIRTUAL_PREFIX)
}

/**
 * The attachment source a virtual folder groups, or null for anything that is not one.
 *
 * BandSpaceFolderVirtualFoldersListener builds the ids as `virtual:{source}` from the same source names
 * the collection's `source` filter accepts, which is what makes the suffix usable as the filter value.
 * A song's node under « Titres » is `virtual:song:{songId}`, so it answers `song` too: it lists a
 * part of that source.
 *
 * @param {string|null} folderId
 * @returns {string|null}
 */
export function virtualFolderSource(folderId) {
  return isVirtualFolderId(folderId)
    ? folderId.slice(VIRTUAL_PREFIX.length).split(SOURCE_ID_SEPARATOR, 1)[0]
    : null
}

/**
 * The selection that opens a source's virtual folder. The inverse of virtualFolderSource(), here so the
 * prefix is spelled in one file rather than interpolated wherever a source has to be turned into a
 * place.
 *
 * @param {string} source
 * @returns {string}
 */
export function virtualFolderId(source) {
  return `${VIRTUAL_PREFIX}${source}`
}

/**
 * The selection that opens one song's node under « Titres » (#1431), or one of that song's folders
 * (#1451). Still a virtual folder: it holds attachments, so nothing can be created in it and its rows
 * come from anywhere in the tree.
 *
 * @param {string} songId
 * @param {string|null} songFileFolderId
 * @returns {string}
 */
export function songFolderId(songId, songFileFolderId = null) {
  const songNode = `${virtualFolderId(SONG_SOURCE)}${SOURCE_ID_SEPARATOR}${songId}`

  return songFileFolderId ? `${songNode}${SOURCE_ID_SEPARATOR}${songFileFolderId}` : songNode
}

/** What a song's node or one of its folders carries after `virtual:song:`, split in two. */
function songSelectionParts(folderId) {
  const prefix = songFolderId('')
  if (typeof folderId !== 'string' || !folderId.startsWith(prefix)) return []

  return folderId.slice(prefix.length).split(SOURCE_ID_SEPARATOR)
}

/**
 * The song a song's node, or one of its folders, lists; null for any other selection, « Titres »
 * itself included. The inverse of songFolderId().
 *
 * @param {string|null} folderId
 * @returns {string|null}
 */
export function virtualFolderSongId(folderId) {
  return songSelectionParts(folderId)[0] || null
}

/**
 * The song folder a selection opens (#1451), or null for the song's node and anything else.
 *
 * @param {string|null} folderId
 * @returns {string|null}
 */
export function virtualFolderSongFileFolderId(folderId) {
  return songSelectionParts(folderId)[1] || null
}
