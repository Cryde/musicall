/**
 * What a live message signal should make the inbox do (#989).
 *
 * Separated from the store so the policy can be tested without a Pinia harness and an API double.
 * The store does the fetching; this decides what is worth fetching.
 */
export function messageSignalPlan({ inboxLoaded, signalThreadId, openThreadId, tabVisible }) {
  // Never opened, so there is nothing on screen to refresh. Without this a signal would fetch the
  // thread list for somebody who is reading the forum.
  if (!inboxLoaded) {
    return { refreshInbox: false, refreshOpenThread: false, markRead: false }
  }

  // A null id is a reconnect, or a body we could not read: we know something was missed but not
  // what, so anything on screen is stale until proven otherwise. Naming a different thread is the
  // only case where the open conversation is known not to have changed.
  const isTheThreadOnScreen =
    Boolean(openThreadId) && (!signalThreadId || signalThreadId === openThreadId)

  return {
    refreshInbox: true,
    refreshOpenThread: isTheThreadOnScreen,
    // Only when the tab is in front. A message arriving on a thread left open in a background tab
    // has not been seen by anybody, and marking it read would make the unread count lie.
    markRead: isTheThreadOnScreen && tabVisible
  }
}

/**
 * Is this message already on screen?
 *
 * The sender's own signal can beat their own POST response back, because the server publishes inside
 * the send and answers afterwards, so the refetch it triggers can already have added the message.
 *
 * Matched on `@id`, which API Platform emits whatever the serialization groups say, unlike `id`,
 * which the message collection does not carry. A message without one is treated as new: pushing a
 * duplicate is recoverable on the next refetch, silently swallowing somebody's message is not.
 */
export function isMessageAlreadyListed(messages, message) {
  const iri = message?.['@id']
  if (!iri) {
    return false
  }

  return messages.some((listed) => listed['@id'] === iri)
}
