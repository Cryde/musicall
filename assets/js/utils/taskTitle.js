/**
 * A task title as it is saved: on one line and trimmed. The title field grows to wrap a long title, so
 * it is a textarea, and a textarea keeps the line breaks of a pasted text where an input dropped them.
 *
 * @param {string | null | undefined} text
 * @returns {string}
 */
export function singleLineTitle(text) {
  return (text ?? '').replace(/\s*[\r\n]+\s*/g, ' ').trim()
}
