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
 * The label is the accessible name: an emoji on its own gives a screen reader nothing to read, and a
 * PrimeVue tooltip gives no accessible name at all (#688).
 */
export const CHAT_REACTIONS = Object.freeze([
  { key: 'thumbs_up', emoji: '👍', label: 'Pouce levé' },
  { key: 'thumbs_down', emoji: '👎', label: 'Pouce baissé' },
  { key: 'heart', emoji: '❤️', label: 'Cœur' },
  { key: 'laugh', emoji: '😂', label: 'Rire' },
  { key: 'party', emoji: '🎉', label: 'Fête' },
  { key: 'fire', emoji: '🔥', label: 'Feu' },
  { key: 'guitar', emoji: '🎸', label: 'Guitare' },
  { key: 'check', emoji: '✅', label: 'Validé' }
])

/** The French name of a reaction, for the aria-label on a pill the API only sent a character for. */
export function chatReactionLabel(key) {
  return CHAT_REACTIONS.find((reaction) => reaction.key === key)?.label ?? 'Réaction'
}
