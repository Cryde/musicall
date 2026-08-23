/**
 * What the Files sidebar can have selected, and which folder each selection means.
 *
 * A real folder is selected by its uuid. The other rows are not folders, so they carry reserved ids
 * instead: the root of the tree, the trash, and the virtual source folders. No selection at all is the
 * flat listing of the whole space, which is a view rather than a place.
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
