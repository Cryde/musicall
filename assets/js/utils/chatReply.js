import { htmlToText } from './messageText.js'

/** The server caps the excerpt of a `reply_to` at the same length (#1377). */
const EXCERPT_MAX_LENGTH = 140

/**
 * The quote a reply will carry, built from a message on screen, in the shape the API sends as
 * `reply_to` (#1377). The banner above the composer shows it before the server has said anything.
 *
 * @param {object} message a ChatMessage as the list holds it
 * @returns {{id: string, author_id: string, author_display_name: string, excerpt: string, has_image: boolean, has_voice_note: boolean, is_deleted: boolean}}
 */
export function replyQuoteFromMessage(message) {
  const isDeleted = Boolean(message.is_deleted)
  const text = isDeleted ? '' : htmlToText(message.content).replace(/\s+/g, ' ').trim()

  return {
    id: message.id,
    author_id: message.author_id,
    author_display_name: message.author_display_name,
    excerpt: text.length > EXCERPT_MAX_LENGTH ? `${text.slice(0, EXCERPT_MAX_LENGTH - 1)}…` : text,
    has_image: !isDeleted && Boolean(message.image),
    has_voice_note: !isDeleted && Boolean(message.voice_note),
    is_deleted: isDeleted
  }
}

/**
 * The quotes of the held replies rebuilt from fresh copies of the messages they answer: an edit or
 * a delete reaches the quotes on screen without reloading the page.
 *
 * @param {ReadonlyArray<object>} messages the messages the pane holds
 * @param {ReadonlyArray<object>} answered messages just re-read or deleted
 * @returns {object[]}
 */
export function withQuotesRefreshed(messages, answered) {
  const answeredById = new Map(answered.map((message) => [message.id, message]))

  return messages.map((message) => {
    const source = message.reply_to ? answeredById.get(message.reply_to.id) : undefined

    return source ? { ...message, reply_to: replyQuoteFromMessage(source) } : message
  })
}

/**
 * What a quote says: its excerpt, or a word for a message without text. A deleted message says so
 * first, since its media flags are emptied with it.
 *
 * @param {{excerpt: string, has_image: boolean, has_voice_note: boolean, is_deleted: boolean}} quote
 * @param {(key: string) => string} t
 * @returns {string}
 */
export function replyQuoteText(quote, t) {
  if (quote.is_deleted) return t('band_space_chat.reply.deleted')
  if (quote.excerpt) return quote.excerpt
  if (quote.has_voice_note) return t('band_space_chat.reply.voice_note')
  if (quote.has_image) return t('band_space_chat.reply.photo')

  return t('band_space_chat.reply.attachment')
}
