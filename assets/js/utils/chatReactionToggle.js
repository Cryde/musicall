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

/**
 * The tap on a reaction pill, shared by the band chat and a direct conversation (#968, #1245).
 *
 * Optimistic, because a tap has to answer instantly. Everything is looked up by message id rather
 * than by index, including the rollback: a live signal can replace the whole list while the request
 * is in flight. A failure is undone against the row on screen, see rolledBackReactions(), and only
 * reported when the message is still held, so an error about a tap made elsewhere never lands on the
 * conversation that replaced it.
 *
 * Keyed `messageId:emoji`, so tapping the same pill twice before the first call answers is ignored
 * while tapping a different one is not.
 *
 * @param {object} options
 * @param {(messageId: string) => object | undefined} options.findMessage the held message, if any
 * @param {(messageId: string, reactions: object[]) => void} options.patchReactions
 * @param {(messageId: string, emojiKey: string, context: unknown) => Promise<object>} options.add
 *   answers with the whole message, so counts others left meanwhile land too
 * @param {(messageId: string, emojiKey: string, context: unknown) => Promise<unknown>} options.remove
 * @param {(error: unknown) => void} options.onError
 */
export function createReactionToggle({ findMessage, patchReactions, add, remove, onError }) {
  const pendingKeys = new Set()

  async function toggle(messageId, emojiKey, context = null) {
    const pendingKey = `${messageId}:${emojiKey}`
    const held = findMessage(messageId)
    if (!held || pendingKeys.has(pendingKey)) {
      return
    }

    const wasReacted = hasReacted(held.reactions, emojiKey)
    pendingKeys.add(pendingKey)
    patchReactions(messageId, toggledReactions(held.reactions, emojiKey))

    try {
      if (wasReacted) {
        await remove(messageId, emojiKey, context)

        return
      }
      const updated = await add(messageId, emojiKey, context)
      patchReactions(messageId, updated?.reactions ?? [])
    } catch (e) {
      const live = findMessage(messageId)
      if (live) {
        patchReactions(messageId, rolledBackReactions(live.reactions, emojiKey, wasReacted))
        onError(e)
      }
    } finally {
      pendingKeys.delete(pendingKey)
    }
  }

  /** The messages with a tap in flight, whose pills a refetch must leave alone (withRefreshedHeld). */
  function pendingMessageIds() {
    return new Set([...pendingKeys].map((key) => key.split(':')[0]))
  }

  function reset() {
    pendingKeys.clear()
  }

  return { toggle, pendingMessageIds, reset }
}
