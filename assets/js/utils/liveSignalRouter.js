/**
 * Where a live signal on the user's own topic goes. One topic carries every kind of update and the SSE
 * frame never says which topic matched, so the payload's `type` is the only router.
 *
 * Out of the store so the routing tests without Pinia: the store supplies the handlers, this decides
 * which of them run.
 *
 * @param {object | null} payload the update's body, or null on a reconnect, where anything published
 *   while we were down is gone for good and everything is refreshed
 * @param {object} handlers
 * @param {() => unknown} handlers.refreshBell
 * @param {() => unknown} handlers.refreshNotificationCounts the navbar and sidebar badges
 * @param {(threadId: string | null) => unknown} handlers.inboxMessage
 * @param {(bandSpaceId: string | null) => unknown} handlers.chatMessage
 * @param {(bandSpaceId: string) => unknown} handlers.chatRead
 * @param {(change: {bandSpaceId: string, messageId: string, change: string}) => unknown} handlers.chatMessageChanged
 */
export function routeLiveSignal(payload, handlers) {
  const type = payload?.type ?? null

  if (type === null || type === 'notification') {
    handlers.refreshBell()
  }
  if (type === null || type === 'message') {
    // The navbar count, which is live even for somebody who never opens the inbox, and then the
    // inbox itself, which no-ops when it was never opened.
    handlers.refreshNotificationCounts()
    handlers.inboxMessage(payload?.thread_id ?? null)
  }
  if (type === null || type === 'band_space_message') {
    // A Band Space channel, which both panes can be showing: the band space tab, keyed by space,
    // and since #994 the inbox, keyed by thread. Each no-ops when its own pane is not on screen,
    // and they never are at once, since they are different routes.
    //
    // The chat store refreshes the sidebar badge itself, because whether the member is looking at
    // the conversation is also what decides whether the signal marks it read.
    handlers.chatMessage(payload?.band_space_id ?? null)
    handlers.inboxMessage(payload?.thread_id ?? null)
  }
  // Somebody else read a channel (#977). Only the band space tab draws « Vu par », so the inbox never
  // hears it, and nothing here touches a badge: their read is not new content for you. Not part of
  // a reconnect either, which already refetches the chat through the message branch above.
  if (type === 'band_space_chat_read' && payload.band_space_id) {
    handlers.chatRead(payload.band_space_id)
  }
  // A message on screen changed in place: a reaction, an edit, a delete, a pin (#1056). Like a read,
  // nothing here touches a badge or marks anything read, since none of it is new content.
  if (type === 'band_space_message_changed' && payload.band_space_id && payload.message_id) {
    handlers.chatMessageChanged({
      bandSpaceId: payload.band_space_id,
      messageId: payload.message_id,
      change: payload.change ?? null
    })
  }
}
