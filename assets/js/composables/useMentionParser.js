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

  function getSuggestions(query, members) {
    if (!query) return []
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

  return { parseToParts, getSuggestions, insertMention }
}
