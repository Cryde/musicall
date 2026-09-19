/**
 * Which chat message the delete endpoint will accept, decided on what the list already holds.
 *
 * The server still has the last word; working it out here is only what lets the bubble show a bin
 * on the messages that can take one, instead of every member hovering a control that answers 403.
 */

/**
 * Mirrors ChatMessageDeleteProcessor: the author, or an administrator of the band space. A message
 * that is already a tombstone takes nobody's delete, which is the 404 that endpoint answers with.
 *
 * Authorship is decided by the caller rather than by comparing ids here, and deliberately. The chat
 * pane already has one notion of « mine », the one that colours the bubble, and the profile that
 * carries the viewer's id arrives asynchronously: checkAuthInfo() fills `user` from the token and
 * calls fetchUserProfile() without awaiting it, so on a first render an id comparison finds nothing
 * and hides the author's own bin until the fetch lands.
 *
 * @param {{is_deleted?: boolean}} message
 * @param {boolean} isAuthor Whether the viewer wrote this message.
 * @param {boolean} isAdmin Whether the viewer administrates the band space.
 * @returns {boolean}
 */
export function canDeleteChatMessage(message, isAuthor, isAdmin) {
  if (!message || message.is_deleted) {
    return false
  }

  return isAuthor || isAdmin
}
