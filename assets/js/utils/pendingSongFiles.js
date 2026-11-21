/**
 * Files picked in « Nouveau titre », held until the new song's page opens and uploads them through the
 * import dialog (#1431): the dialog that picked them closes as the page opens, and an upload needs a
 * screen that stays to show its progress and what was refused.
 */
const pending = new Map()

/**
 * @param {string} songId
 * @param {ReadonlyArray<File>} files
 */
export function holdSongFiles(songId, files) {
  if (files.length > 0) pending.set(songId, [...files])
}

/**
 * The files held for this song, once: a second call, a reload of the page, finds none.
 *
 * @param {string} songId
 * @returns {File[]}
 */
export function takeSongFiles(songId) {
  const files = pending.get(songId) ?? []
  pending.delete(songId)
  return files
}
