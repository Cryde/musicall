import { foldForSearch } from './searchText.js'

/**
 * The conversations a search keeps, on the device as the app does: the inbox is small and the
 * endpoint has no search. A conversation matches by its title (the person, or the band), its
 * channel, or its last message. An empty search keeps them all.
 *
 * @param {object[]} threadMetas the inbox's conversations
 * @param {string} query
 * @param {(threadMeta: object) => string} titleOf what the row is called, from conversationTitle()
 * @returns {object[]}
 */
export function filterConversations(threadMetas, query, titleOf) {
  const needle = foldForSearch(query)
  if (!needle) return threadMetas

  return threadMetas.filter((threadMeta) =>
    [
      titleOf(threadMeta),
      threadMeta.thread?.channel_name,
      threadMeta.thread?.last_message?.content_preview
    ]
      .map(foldForSearch)
      .some((field) => field.includes(needle))
  )
}
