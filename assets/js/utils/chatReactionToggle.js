import { CHAT_REACTIONS } from '../constants/chatReactions.js'

const DISPLAY_ORDER = CHAT_REACTIONS.map((reaction) => reaction.key)

/** Whether the viewer is one of the people counted under this emoji. */
export function hasReacted(reactions, emojiKey) {
  return (reactions ?? []).some((reaction) => reaction.key === emojiKey && reaction.has_reacted)
}

/**
 * The reaction row as it will look once the tap lands, so the pill answers straight away instead of
 * waiting for the round trip. The store rolls back to the previous row if the request fails.
 *
 * It applies the same two rules the server does, because the optimistic row and the one that comes
 * back have to look identical or the pills would visibly jump: the order is the palette's, and an
 * emoji nobody uses is absent rather than present with a zero.
 */
export function toggledReactions(reactions, emojiKey) {
  const current = reactions ?? []
  const held = current.find((reaction) => reaction.key === emojiKey)

  if (held?.has_reacted) {
    return current
      .map((reaction) =>
        reaction.key === emojiKey
          ? { ...reaction, count: reaction.count - 1, has_reacted: false }
          : reaction
      )
      .filter((reaction) => reaction.count > 0)
  }

  if (held) {
    return current.map((reaction) =>
      reaction.key === emojiKey
        ? { ...reaction, count: reaction.count + 1, has_reacted: true }
        : reaction
    )
  }

  const added = CHAT_REACTIONS.find((reaction) => reaction.key === emojiKey)
  if (!added) {
    return current
  }

  return [...current, { key: added.key, emoji: added.emoji, count: 1, has_reacted: true }].sort(
    (left, right) => DISPLAY_ORDER.indexOf(left.key) - DISPLAY_ORDER.indexOf(right.key)
  )
}

/**
 * The row to show after a failed toggle.
 *
 * Not the row captured before the tap: a live signal can refetch the page while the request is in
 * flight, and `mergeMessages` takes the fetched copy for a message it already holds, so pasting the
 * frozen copy back would also erase a reaction another member left in that window. Inverting the
 * optimistic change against whatever is on screen now adjusts only the viewer's own contribution and
 * leaves everybody else's alone.
 *
 * And it inverts nothing when the refetch has already put the server's own row up, which is what
 * `has_reacted` having fallen back to where it started says. Toggling then would invent a state
 * nobody is in.
 */
export function rolledBackReactions(reactions, emojiKey, wasReacted) {
  if (hasReacted(reactions, emojiKey) === wasReacted) {
    return reactions
  }

  return toggledReactions(reactions, emojiKey)
}
