import { i18n } from '../i18n/index.js'

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

  if (names.length === 1) {
    return i18n.global.t('band_space_chat.read_receipt.one', { name: names[0] })
  }

  if (names.length <= MAX_NAMES) {
    return i18n.global.t('band_space_chat.read_receipt.several', {
      names: names.slice(0, -1).join(', '),
      last: names.at(-1)
    })
  }

  const shown = names.slice(0, MAX_NAMES - 1)

  return i18n.global.t('band_space_chat.read_receipt.many', {
    names: shown.join(', '),
    count: names.length - shown.length
  })
}
