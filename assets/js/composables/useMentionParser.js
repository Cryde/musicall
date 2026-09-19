const MENTION_REGEX = /@\[([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\]/gi

export function useMentionParser() {
  /**
   * The stored content as text and mentions, for anything that has to show it: the read only view
   * renders the username, the editor builds a chip from the pair (#1006).
   *
   * Carries the `userId` as well as the name because those are two different things. The name is what
   * a reader sees and it can be missing; the id is the content, and it survives an edit whether or not
   * the roster could name it.
   *
   * Note this reads a uuid and nothing else, so the chat's `@[tous]` sentinel comes back as plain
   * text. That is deliberate rather than an oversight: the sentinel is write only today, nothing
   * seeds the chat, and widening the pattern would turn a literal `@[tous]` typed into a task comment
   * into a mention the server never agreed to. Chat editing (#966) is where it has to be faced.
   */
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
      // Case insensitively, because the pattern above accepts a uuid in either case and the server
      // lowercases before comparing (TaskCommentMentionRecorder). A member it would notify has to be
      // one this can name, or the mention renders as `@inconnu` to everybody reading it.
      const mentionedId = match[1].toLowerCase()
      const member = members.find((m) => m.user_id.toLowerCase() === mentionedId)
      parts.push({
        type: 'mention',
        userId: match[1],
        username: member ? member.username : 'inconnu'
      })
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

  return { parseToParts, findMentionQuery, getSuggestions }
}
