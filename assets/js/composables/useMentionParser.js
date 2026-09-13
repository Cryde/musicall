const MENTION_REGEX = /@\[([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\]/gi

export function useMentionParser() {
  function parseToParts(rawContent, members) {
    if (!rawContent) return []

    const parts = []
    let lastIndex = 0
    let match

    MENTION_REGEX.lastIndex = 0
    // biome-ignore lint/suspicious/noAssignInExpressions: canonical regex iteration pattern
    while ((match = MENTION_REGEX.exec(rawContent)) !== null) {
      if (match.index > lastIndex) {
        parts.push({ type: 'text', value: rawContent.slice(lastIndex, match.index) })
      }
      const member = members.find((m) => m.user_id === match[1])
      parts.push({ type: 'mention', username: member ? member.username : 'inconnu' })
      lastIndex = match.index + match[0].length
    }

    if (lastIndex < rawContent.length) {
      parts.push({ type: 'text', value: rawContent.slice(lastIndex) })
    }

    return parts
  }

  /**
   * The mention being typed just before the cursor, or null when there is not one.
   *
   * Shared by both composers rather than copied into each, because it is one rule about one gesture:
   * a rule that lives in two places is a rule that gets fixed in one of them.
   *
   * An `@` only opens a mention at the start of the text or after whitespace. Without that, every
   * email address anybody typed would open the roster, which matters now that a bare `@` is enough to
   * open it at all. The two closing rules are the pre-existing ones: a space ends the mention, and a
   * `[` means the caret is sitting inside an `@[uuid]` that has already been inserted.
   *
   * @param {string} textBeforeCursor
   * @returns {string|null} the query typed after the `@`, possibly empty
   */
  function findMentionQuery(textBeforeCursor) {
    const atIndex = textBeforeCursor.lastIndexOf('@')
    if (atIndex === -1) {
      return null
    }

    const isAtWordStart = atIndex === 0 || /\s/.test(textBeforeCursor[atIndex - 1])
    const trigger = textBeforeCursor.slice(atIndex)
    if (!isAtWordStart || trigger.includes(' ') || trigger.includes('[')) {
      return null
    }

    return trigger.slice(1)
  }

  /**
   * A plain filter: an empty query matches everybody, so a bare `@` opens the whole roster the way
   * every other chat does. Whether that is wanted is the caller's business, not this function's, and
   * returning nothing for an empty query used to hide `@tous` behind guessing that you had to type
   * `@t` to find it.
   */
  function getSuggestions(query, members) {
    if (!query) return members
    const lower = query.toLowerCase()
    return members.filter((m) => m.username.toLowerCase().startsWith(lower))
  }

  function insertMention(text, cursorPos, member) {
    const before = text.slice(0, cursorPos)
    const after = text.slice(cursorPos)
    const atIndex = before.lastIndexOf('@')
    if (atIndex === -1) return { text, cursor: cursorPos }

    const newBefore = `${before.slice(0, atIndex)}@[${member.user_id}] `
    return {
      text: newBefore + after,
      cursor: newBefore.length
    }
  }

  return { parseToParts, findMentionQuery, getSuggestions, insertMention }
}
