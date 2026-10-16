import { i18n } from '../i18n/index.js'

/**
 * The reactions a chat message accepts (#968).
 *
 * A closed list rather than free emoji input: free input means arbitrary user text stored and
 * rendered on every message, and a reaction is only worth anything when several people pick the
 * *same* one so it collapses into a single count.
 *
 * This list is the client half of a pair. `App\Enum\Message\MessageReactionEmoji` is the other, and
 * the API hands the character out with each aggregate, but the picker needs the whole palette
 * whether or not anybody has used it yet. The two are kept in step by
 * tests/Unit/Enum/Message/ChatReactionPaletteTest.php. Edit both, in the same order.
 *
 * The label is the accessible name, read through `chatReactionLabel()`: an emoji on its own gives a screen reader nothing to read, and a
 * PrimeVue tooltip gives no accessible name at all (#688).
 */
export const CHAT_REACTIONS = Object.freeze([
  { key: 'thumbs_up', emoji: '👍', labelKey: 'band_space_chat.reaction.thumbs_up' },
  { key: 'thumbs_down', emoji: '👎', labelKey: 'band_space_chat.reaction.thumbs_down' },
  { key: 'heart', emoji: '❤️', labelKey: 'band_space_chat.reaction.heart' },
  { key: 'laugh', emoji: '😂', labelKey: 'band_space_chat.reaction.laugh' },
  { key: 'party', emoji: '🎉', labelKey: 'band_space_chat.reaction.party' },
  { key: 'fire', emoji: '🔥', labelKey: 'band_space_chat.reaction.fire' },
  { key: 'guitar', emoji: '🎸', labelKey: 'band_space_chat.reaction.guitar' },
  { key: 'check', emoji: '✅', labelKey: 'band_space_chat.reaction.check' }
])

/** The name of a reaction, for the aria-label on a pill the API only sent a character for. */
export function chatReactionLabel(key) {
  const reaction = CHAT_REACTIONS.find((candidate) => candidate.key === key)

  return i18n.global.t(reaction?.labelKey ?? 'band_space_chat.reaction.fallback')
}
