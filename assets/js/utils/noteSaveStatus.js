/**
 * What the last save of each open note answered, kept per note id.
 *
 * A note body is saved by a timer, so a save can land well after its note has been left: the last
 * one of a writing session goes out on the way out. A single status for the whole module therefore
 * described whichever note happened to be open rather than the one that was written, which put an
 * error badge on an untouched note and, worse, said nothing at all about the one that was lost.
 *
 * Two questions are asked of these statuses, and they deliberately count different things:
 *
 * - `failedSaveNoteIds` is text nothing is going to save, so anything unmounting the editor holding
 *   it loses it, whether that is leaving the module or opening another note, and the member has to
 *   be asked first;
 * - `holdsUnsavedContent` also counts a save still in flight, because a closed tab takes that one
 *   with it just the same. Navigating away does not: the request is already on its way.
 *
 * Pure on purpose, so the rules are tested without a browser. See noteSaveStatus.test.js.
 */

/** Statuses meaning the server does not have this text. */
const UNSAVED_STATUSES = ['saving', 'error', 'conflict']

/** Of those, the ones nothing is going to resolve on its own. */
const FAILED_STATUSES = ['error', 'conflict']

/**
 * @param {Object<string, string>} statuses
 * @param {string|null} noteId
 * @returns {string|null} null for a note that has never been saved, and for no note at all
 */
export function noteSaveStatus(statuses, noteId) {
  return statuses[noteId] ?? null
}

/** @returns {Object<string, string>} a new map, so a watcher sees the change */
export function withNoteSaveStatus(statuses, noteId, status) {
  return { ...statuses, [noteId]: status }
}

/** @returns {Object<string, string>} */
export function withoutNoteSaveStatus(statuses, noteId) {
  const { [noteId]: _dropped, ...rest } = statuses

  return rest
}

/** @returns {string[]} notes holding text that no save is going to write */
export function failedSaveNoteIds(statuses) {
  return Object.keys(statuses).filter((noteId) => FAILED_STATUSES.includes(statuses[noteId]))
}

/** @returns {boolean} true when closing the tab now would lose text */
export function holdsUnsavedContent(statuses) {
  return Object.values(statuses).some((status) => UNSAVED_STATUSES.includes(status))
}
