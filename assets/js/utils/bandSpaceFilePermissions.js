/**
 * Who may act on a band space file: whoever uploaded it, or an admin of the space.
 *
 * This is the rule BandSpaceFileDeleteProcessor and BandSpaceFileRestoreProcessor both enforce. The
 * file list and the detail drawer used to gate on the uploader alone, so an admin could restore from
 * the trash a file the interface would not let them delete, and nobody could reclaim the quota a
 * departed member left behind (#823). One function so the surfaces cannot drift apart again.
 *
 * @param {{created_by?: {id?: string|number}}|null|undefined} file
 * @param {string|number|null|undefined} currentUserId id of the logged in member, if any
 * @param {boolean} isAdmin whether that member is an admin of the band space
 * @returns {boolean}
 */
export function isFileCreatorOrAdmin(file, currentUserId, isAdmin) {
  if (!file) return false
  if (isAdmin) return true

  // Both sides undefined would otherwise compare equal and hand the file to an anonymous visitor.
  return Boolean(currentUserId) && file.created_by?.id === currentUserId
}
