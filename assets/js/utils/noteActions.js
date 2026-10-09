/**
 * Mirrors NoteOwnerChecker: a note is deleted by its author or by an administrator of the band
 * space (#1166). Everyone else used to be offered « Supprimer », confirm, then read a refusal. The
 * server still has the last word.
 *
 * @param {{created_by?: {id: string}}} note
 * @param {string|null} currentUserId
 * @param {boolean} isAdmin Whether the current member administrates the band space.
 * @returns {boolean}
 */
export function canDeleteNote(note, currentUserId, isAdmin) {
  if (isAdmin) {
    return true
  }

  return Boolean(currentUserId) && note?.created_by?.id === currentUserId
}
