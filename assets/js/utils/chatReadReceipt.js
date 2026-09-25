/**
 * The « Vu par » line the chat pane prints under its last message (#977).
 *
 * Only the last one, which is how a read receipt reads everywhere: the question it answers is always
 * about what was said most recently, and repeating it under every bubble is noise.
 */

/** Past three, the names make room for a count, so the suffix is never « et 1 autre ». */
const MAX_NAMES = 3

/**
 * @param {string[]|undefined} readByUsernames Who the server says has read the message.
 * @returns {string} Empty when nobody has, which is what the pane renders nothing for.
 */
export function readReceiptLabel(readByUsernames) {
  const names = readByUsernames ?? []
  if (names.length === 0) {
    return ''
  }

  if (names.length <= MAX_NAMES) {
    return `Vu par ${joinWithAnd(names)}`
  }

  const shown = names.slice(0, MAX_NAMES - 1)

  return `Vu par ${shown.join(', ')} et ${names.length - shown.length} autres`
}

/**
 * @param {string[]} names
 * @returns {string}
 */
function joinWithAnd(names) {
  if (names.length === 1) {
    return names[0]
  }

  return `${names.slice(0, -1).join(', ')} et ${names.at(-1)}`
}
