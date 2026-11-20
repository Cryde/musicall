/**
 * « Ajouter une tablature » has no folder on screen to default to, so it proposes the last one chosen
 * from Tablatures, per space (#1430). The root is no entry at all.
 */
import { LAST_TAB_FOLDER_KEY } from '../constants/bandSpace.js'
import { treeHoldsFolder } from './fileListing.js'

function storageKey(bandSpaceId) {
  return `${LAST_TAB_FOLDER_KEY}:${bandSpaceId}`
}

/** Read inside the try: a browser blocking site data throws on `window.localStorage` itself. */
function browserStorage() {
  return window.localStorage
}

/**
 * The remembered folder, or null for the root. A folder deleted or out of reach since is forgotten,
 * since the upload would be refused.
 *
 * @param {string} bandSpaceId
 * @param {Array} folders the space's folder tree
 * @param {() => Storage} storage
 * @returns {string|null}
 */
export function rememberedTabFolder(bandSpaceId, folders, storage = browserStorage) {
  let folderId = null
  try {
    folderId = storage().getItem(storageKey(bandSpaceId))
  } catch {
    return null
  }

  return treeHoldsFolder(folders, folderId) ? folderId : null
}

/**
 * @param {string} bandSpaceId
 * @param {string|null} folderId null for the root
 * @param {() => Storage} storage
 */
export function rememberTabFolder(bandSpaceId, folderId, storage = browserStorage) {
  try {
    if (folderId) {
      storage().setItem(storageKey(bandSpaceId), folderId)
    } else {
      storage().removeItem(storageKey(bandSpaceId))
    }
  } catch {
    // Storage refused (private mode, quota): the dialog proposes the root next time.
  }
}
