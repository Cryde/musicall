import { htmlToText } from './messageText.js'

/** The server caps the excerpt of a `reply_to` at the same length (#1377). */
const EXCERPT_MAX_LENGTH = 140

/**
 * Who wrote a message of the band chat, which carries its author flat.
 *
 * @param {{author_id: string, author_display_name: string}} message
 * @returns {{id: string, displayName: string}}
 */
export function bandChatAuthor(message) {
  return { id: message.author_id, displayName: message.author_display_name }
}

/**
 * Who wrote a message of a conversation (#1380), which embeds its author.
 *
 * @param {{author?: {id: string, display_name: string}}} message
 * @returns {{id: string, displayName: string}}
 */
export function conversationAuthor(message) {
  return { id: message.author?.id, displayName: message.author?.display_name }
}

/**
 * The quote a reply will carry, built from a message on screen, in the shape the API sends as
 * `reply_to` (#1377). The banner above the composer shows it before the server has said anything.
 *
 * @param {object} message a message as the list holds it
 * @param {(message: object) => {id: string, displayName: string}} authorOf bandChatAuthor or conversationAuthor
 * @returns {{id: string, author_id: string, author_display_name: string, excerpt: string, has_image: boolean, has_voice_note: boolean, is_deleted: boolean}}
 */
export function replyQuoteFromMessage(message, authorOf = bandChatAuthor) {
  const isDeleted = Boolean(message.is_deleted)
  const text = isDeleted ? '' : htmlToText(message.content).replace(/\s+/g, ' ').trim()
  const author = authorOf(message)

  return {
    id: message.id,
    author_id: author.id,
    author_display_name: author.displayName,
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
 * @param {(message: object) => {id: string, displayName: string}} authorOf see replyQuoteFromMessage()
 * @returns {object[]}
 */
export function withQuotesRefreshed(messages, answered, authorOf = bandChatAuthor) {
  const answeredById = new Map(answered.map((message) => [message.id, message]))

  return messages.map((message) => {
    const source = message.reply_to ? answeredById.get(message.reply_to.id) : undefined

    return source ? { ...message, reply_to: replyQuoteFromMessage(source, authorOf) } : message
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
