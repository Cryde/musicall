import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import messageApi from '../../api/message/message.js'
import { handleApiError } from '../../api/utils/handleApiError.js'
import { i18n } from '../../i18n/index.js'
import { createReactionToggle } from '../../utils/chatReactionToggle.js'
import {
  conversationAuthor,
  replyQuoteFromMessage,
  withQuotesRefreshed
} from '../../utils/chatReply.js'
import { createTypingNotifier, createTypistTracker } from '../../utils/chatTyping.js'
import {
  isHeld,
  isShownOnScreen,
  mayMergeNewestPage,
  paneAfterWindow,
  withRefreshedHeld
} from '../../utils/chatWindow.js'
import { createCoalescedCall } from '../../utils/coalescedCall.js'
import { isChannel } from '../../utils/conversationIdentity.js'
import {
  hasOlderToLoad,
  mergeMessages,
  nextOlderPageToLoad
} from '../../utils/messagePagination.js'
import { isMessageAlreadyListed, messageSignalPlan } from '../../utils/messageSignal.js'
import { useNotificationStore } from '../notification/notification.js'
import { useUserSecurityStore } from '../user/security.js'

/** Changes signalled within this window are re-read together, as in the band chat (#1056). */
const MESSAGE_CHANGE_COALESCE_MS = 1000

/** A presence refusal means there is nobody to show: a block, or a conversation that is gone (#1246). */
const PRESENCE_REFUSED_STATUSES = [403, 404]

export const useMessageStore = defineStore('message', () => {
  const threads = ref([])
  const messages = ref([])
  const currentThreadId = ref(null)
  const currentThreadMetaId = ref(null)
  const isLoading = ref(false)
  // Distinct from `threads.length`, which cannot tell "never opened the inbox" from "opened it and
  // has no conversations yet". The second one still has to light up on a first ever message.
  const hasLoadedThreads = ref(false)

  // Both lists are replaced wholesale by their loader, so an older response landing after a newer one
  // would put the stale version on screen and drop the message that just arrived. Signals arrive in
  // bursts during an ordinary fast exchange, which is exactly when that happens. Same guard the band
  // space stores use.
  let threadsRequestId = 0
  let messagesRequestId = 0
  const isLoadingMessages = ref(false)
  // Its own flag, not isLoadingMessages: that one drives a v-if that takes the whole conversation out
  // of the DOM, which is the opposite of what loading older messages should look like.
  const isLoadingOlderMessages = ref(false)
  // And its own error, so a page that fails to load leaves the conversation on screen (#955).
  const loadOlderError = ref(null)
  const totalMessages = ref(0)
  const isAddingMessage = ref(false)
  const reactionError = ref(null)

  // Whether `messages` runs back from the newest message with no gap, as in the band chat (#1039). It
  // does, except after a jump to a quoted message above the loaded pages (#1380), when the pane holds a
  // window in the middle of the history: reading on then goes through the window endpoint, and a live
  // signal must not splice today's page into it.
  const isAtTail = ref(true)
  const windowHasOlder = ref(false)
  const windowHasNewer = ref(false)
  const isLoadingNewerMessages = ref(false)
  const loadNewerError = ref(null)
  const jumpError = ref(null)
  // What the conversation should scroll to and light up: a fresh object each time, so asking for the
  // same message twice still moves the pane.
  const focusRequest = ref(null)
  // The message the composer answers (#1380), as the quote the reply will carry. Kept through a failed
  // send, like the draft it belongs to.
  const replyTarget = ref(null)

  // Newest conversation first.
  const orderedThreads = computed(() =>
    [...threads.value].sort((a, b) => {
      const dateA = new Date(a.thread.last_message?.creation_datetime || 0)
      const dateB = new Date(b.thread.last_message?.creation_datetime || 0)
      return dateB - dateA
    })
  )

  const hasOlderMessages = computed(() =>
    isAtTail.value
      ? hasOlderToLoad(messages.value.length, totalMessages.value)
      : windowHasOlder.value
  )
  const hasNewerMessages = computed(() => !isAtTail.value && windowHasNewer.value)

  const currentThread = computed(() => {
    return threads.value.find((t) => t.thread.id === currentThreadId.value)
  })

  /**
   * `silent` is for a refresh the user did not ask for (#989).
   *
   * The spinner replaces the whole list rather than sitting beside it, so toggling it on a live
   * update makes the inbox disappear and come back every time somebody types. A background refresh
   * also leaves the old data alone when it fails: blanking a list that is on screen because one
   * request timed out is worse than showing something a few seconds stale.
   */
  async function loadThreads({ silent = false } = {}) {
    const currentRequestId = ++threadsRequestId
    if (!silent) {
      isLoading.value = true
    }
    try {
      const response = await messageApi.getThreads()
      if (currentRequestId !== threadsRequestId) return
      threads.value = response.member || []
      hasLoadedThreads.value = true
    } catch (e) {
      console.error('Failed to load threads:', e)
      if (!silent && currentRequestId === threadsRequestId) {
        threads.value = []
      }
    } finally {
      if (!silent && currentRequestId === threadsRequestId) {
        isLoading.value = false
      }
    }
  }

  async function selectThread(threadMeta) {
    if (threadMeta.thread.id !== currentThreadId.value) forgetMessageState()
    currentThreadId.value = threadMeta.thread.id
    currentThreadMetaId.value = threadMeta.id

    // A channel is shown by the band chat itself (#1244), which loads it and marks it read; the row
    // only has to stop showing a count for it.
    if (isChannel(threadMeta)) {
      // A direct conversation still loading must not land in here once the channel is open.
      ++messagesRequestId
      messages.value = []
      totalMessages.value = 0
      loadOlderError.value = null
      isLoadingMessages.value = false
      threadMeta.unread_count = 0
      return
    }

    await loadMessages(threadMeta.thread.id)

    // Mark as read if anything is unread. Still driven by arriving on the thread rather than by
    // scrolling to the new message, which is what it did before the count replaced the boolean (#954).
    if (threadMeta.unread_count > 0) {
      await markAsRead(threadMeta.id)
    }
  }

  /** Same rule as loadThreads(): a refresh nobody asked for neither blanks the pane nor wipes it. */
  async function loadMessages(threadId, { silent = false } = {}) {
    // A window, or a jump on its way to one, has no use for the newest page (#1380), and bumping the
    // generation for it would drop the jump's answer.
    if (silent && !isAtTail.value) return
    const currentRequestId = ++messagesRequestId
    if (!silent) {
      isLoadingMessages.value = true
    }
    try {
      const response = await messageApi.getMessages({ threadId })
      // The newest page only belongs in a pane that runs back from the newest message (#1380).
      if (
        currentRequestId !== messagesRequestId ||
        !mayMergeNewestPage({ silent, isAtTail: isAtTail.value })
      ) {
        return
      }
      // Reverse to show oldest first
      const newest = (response.member || []).reverse()
      // A silent reload is a live signal refetching the newest page, and the reader may have loaded
      // history behind it. Replacing would throw that away mid-read, so merge instead (#989).
      messages.value = silent ? mergeMessages(messages.value, newest) : newest
      totalMessages.value = response.totalItems ?? newest.length
      loadOlderError.value = null
      if (!silent) resetWindow()
    } catch (e) {
      console.error('Failed to load messages:', e)
      if (!silent && currentRequestId === messagesRequestId) {
        messages.value = []
        totalMessages.value = 0
      }
    } finally {
      if (!silent && currentRequestId === messagesRequestId) {
        isLoadingMessages.value = false
      }
    }
  }

  /**
   * One page further back through the conversation (#955).
   *
   * The page is derived from what is loaded rather than counted up, so live messages arriving at the
   * head cannot make it drift, and the result is merged rather than appended, so the rows the window
   * repeats are dropped instead of shown twice. See messagePagination.js.
   */
  async function loadOlderMessages() {
    const threadId = currentThreadId.value
    if (!threadId || isLoadingOlderMessages.value || !hasOlderMessages.value) {
      return
    }

    // Joins the same generation the other loaders use. Checking the thread id alone is not enough:
    // leave this thread and come back while the page is in flight and the id matches again, but
    // `messages` now holds whatever the thread in between loaded, and this would merge into that.
    const currentRequestId = ++messagesRequestId
    isLoadingOlderMessages.value = true
    loadOlderError.value = null
    try {
      if (!isAtTail.value) {
        const page = await messageApi.getMessageWindow(threadId, { before: messages.value[0]?.id })
        if (currentRequestId !== messagesRequestId || threadId !== currentThreadId.value) return
        messages.value = mergeMessages(messages.value, page.messages ?? [])
        windowHasOlder.value = page.has_older

        return
      }

      const response = await messageApi.getMessages({
        threadId,
        page: nextOlderPageToLoad(messages.value.length)
      })
      if (currentRequestId !== messagesRequestId || threadId !== currentThreadId.value) return
      messages.value = mergeMessages(messages.value, (response.member || []).reverse())
      totalMessages.value = response.totalItems ?? totalMessages.value
    } catch (e) {
      console.error('Failed to load older messages:', e)
      loadOlderError.value = i18n.global.t('message.thread.load_older_error')
    } finally {
      isLoadingOlderMessages.value = false
    }
  }

  /**
   * Reads on towards the present from a window (#1380). Reaching the newest message ends the window,
   * and the conversation is read again, as arriving on it would do.
   */
  async function loadNewerMessages() {
    const threadId = currentThreadId.value
    if (!threadId || isLoadingNewerMessages.value || !hasNewerMessages.value) {
      return
    }

    const currentRequestId = ++messagesRequestId
    isLoadingNewerMessages.value = true
    loadNewerError.value = null
    try {
      const page = await messageApi.getMessageWindow(threadId, { after: messages.value.at(-1)?.id })
      if (currentRequestId !== messagesRequestId || threadId !== currentThreadId.value) return
      messages.value = mergeMessages(messages.value, page.messages ?? [])
      applyWindow(page)
      if (isAtTail.value) await markCurrentAsRead()
    } catch (e) {
      console.error('Failed to load newer messages:', e)
      loadNewerError.value = i18n.global.t('band_space_chat.error.load_newer')
    } finally {
      isLoadingNewerMessages.value = false
    }
  }

  /** The pane runs back from the newest message again, the page arithmetic its own. */
  function resetWindow() {
    isAtTail.value = true
    windowHasOlder.value = false
    windowHasNewer.value = false
  }

  /** After a window answer has been merged in: see paneAfterWindow(). */
  function applyWindow(page) {
    const pane = paneAfterWindow(page, messages.value.length)
    isAtTail.value = pane.isAtTail
    windowHasOlder.value = pane.hasOlder
    windowHasNewer.value = pane.hasNewer
    if (pane.total !== null) {
      totalMessages.value = pane.total
    }
  }

  /**
   * Shows one message in context (#1380), the one a quote answers. Already held, the pane just scrolls
   * to it; otherwise the pane is replaced by a window around it, as in the band chat (#1039).
   */
  async function jumpToMessage(messageId) {
    const threadId = currentThreadId.value
    jumpError.value = null
    if (isHeld(messages.value, messageId)) {
      focusRequest.value = { messageId }

      return
    }

    const currentRequestId = ++messagesRequestId
    // Out of the live end before the request: a message landing meanwhile must not splice today's
    // page into the pane the window is about to replace. Put back if the jump fails.
    const wasAtTail = isAtTail.value
    isAtTail.value = false
    try {
      const page = await messageApi.getMessageWindow(threadId, { around: messageId })
      if (currentRequestId !== messagesRequestId || threadId !== currentThreadId.value) return
      messages.value = page.messages ?? []
      loadOlderError.value = null
      loadNewerError.value = null
      applyWindow(page)
      focusRequest.value = { messageId }
    } catch (e) {
      console.error('Failed to jump to a message:', e)
      if (currentRequestId === messagesRequestId) {
        isAtTail.value = wasAtTail
        // A message that arrived meanwhile was held back for the window that never came.
        if (wasAtTail) loadMessages(threadId, { silent: true })
        jumpError.value =
          e.status === 404
            ? i18n.global.t('band_space_chat.error.message_unavailable')
            : i18n.global.t('band_space_chat.error.jump_failed')
      }
    }
  }

  function dismissJumpError() {
    jumpError.value = null
  }

  /** Back to the live end of the conversation from a window. */
  async function returnToLatest() {
    const threadId = currentThreadId.value
    if (!threadId) return
    await loadMessages(threadId)
    await markCurrentAsRead()
  }

  async function markCurrentAsRead() {
    const threadMeta = threads.value.find((t) => t.thread.id === currentThreadId.value)
    if (threadMeta && threadMeta.unread_count > 0) {
      await markAsRead(threadMeta.id)
    }
  }

  function startReply(message) {
    replyTarget.value = replyQuoteFromMessage(message, conversationAuthor)
  }

  function cancelReply() {
    replyTarget.value = null
  }

  /**
   * The quotes on screen and the banner follow what they answer: a reply cannot be sent to a deleted
   * message, so the banner goes rather than leading to a refused send.
   */
  function refreshQuotesOf(answered) {
    messages.value = withQuotesRefreshed(messages.value, answered, conversationAuthor)
    const target = answered.find((message) => message.id === replyTarget.value?.id)
    if (target) {
      replyTarget.value = target.is_deleted
        ? null
        : replyQuoteFromMessage(target, conversationAuthor)
    }
  }

  /** A direct conversation only: a channel is marked read by the band chat that shows it (#1244). */
  async function markAsRead(threadMetaId) {
    try {
      await messageApi.markThreadAsRead({ threadMetaId })
      const thread = threads.value.find((t) => t.id === threadMetaId)
      if (thread) {
        thread.unread_count = 0
        // Refresh navbar notification count
        const notificationStore = useNotificationStore()
        notificationStore.loadNotifications()
      }
    } catch (e) {
      console.error('Failed to mark thread as read:', e)
    }
  }

  async function postMessage({ recipientId, content, contactOrigin = null }) {
    isAddingMessage.value = true
    try {
      const newMessage = await messageApi.postMessage({ recipientId, content, contactOrigin })
      // Reload threads to get the new/updated thread
      await loadThreads()
      // Return the thread ID so caller can navigate to it
      return newMessage.thread?.id || null
    } catch (error) {
      handleApiError(error)
    } finally {
      isAddingMessage.value = false
    }
  }

  /** `media` is an image or a voice note (#1247), `{ image }` or `{ voiceNote }`. */
  async function postMessageInThread({ threadId, content, media = null }) {
    isAddingMessage.value = true
    const replyTo = replyTarget.value?.id ?? null
    try {
      const newMessage = await messageApi.postMessageInThread({ threadId, content, media, replyTo })
      typingNotifier.reset()
      // Unless another message was picked meanwhile: that reply is still to be written.
      if (replyTarget.value?.id === replyTo) {
        replyTarget.value = null
      }
      // Written from a window up the history: appending it there would open a gap between the window
      // and today. The member wrote it, so they mean to see it, where it really is.
      if (!isAtTail.value) {
        await returnToLatest()
      } else if (!isMessageAlreadyListed(messages.value, newMessage)) {
        // The signal for this very message can arrive before this promise resolves, because the server
        // publishes inside the send and answers afterwards. That refetch has already put the message
        // in the list, so pushing it again would show it twice.
        messages.value.push(newMessage)
      }

      const thread = threads.value.find((t) => t.thread.id === threadId)
      if (thread) {
        thread.thread.last_message = newMessage
      }
    } finally {
      isAddingMessage.value = false
    }
  }

  // --- Edit, delete, react (#1245) ------------------------------------------------------------------

  /**
   * The server's copy lands where the message already is, with its « modifié » and its « Vu ». Errors
   * are left to the edit box, which stays open rather than losing what was typed.
   */
  async function editMessage(messageId, content) {
    const threadId = currentThreadId.value
    const message = await messageApi.updateMessage({ threadId, messageId, content })
    if (threadId === currentThreadId.value) {
      messages.value = mergeMessages(messages.value, [message])
      refreshQuotesOf([message])
    }
  }

  /** A tombstone in place: the row stays, so nobody's scroll position moves (#967). */
  async function deleteMessage(messageId) {
    const threadId = currentThreadId.value
    await messageApi.deleteMessage({ threadId, messageId })
    if (threadId !== currentThreadId.value) return
    const tombstones = []
    messages.value = messages.value.map((message) => {
      if (message.id !== messageId) return message
      const tombstone = { ...message, content: '', is_deleted: true, editable_content: undefined }
      tombstones.push(tombstone)
      return tombstone
    })
    refreshQuotesOf(tombstones)
  }

  function patchReactions(messageId, reactions) {
    messages.value = messages.value.map((message) =>
      message.id === messageId ? { ...message, reactions } : message
    )
  }

  const reactionToggle = createReactionToggle({
    findMessage: (messageId) => messages.value.find((message) => message.id === messageId),
    patchReactions,
    add: (messageId, emoji, threadId) => messageApi.addReaction({ threadId, messageId, emoji }),
    remove: (messageId, emoji, threadId) =>
      messageApi.removeReaction({ threadId, messageId, emoji }),
    onError: (e) => {
      console.error('Failed to toggle a reaction:', e)
      reactionError.value = e.status
        ? e.message
        : i18n.global.t('band_space_chat.error.reaction_failed')
    }
  })

  function toggleReaction(messageId, emojiKey) {
    reactionError.value = null
    return reactionToggle.toggle(messageId, emojiKey, currentThreadId.value)
  }

  /**
   * A message of a conversation changed in place, by either of the two (#1245). The open conversation
   * re-reads the page holding it, quietly, and swaps in only what it already holds. No read and no
   * badge: none of it is new content.
   */
  const changedMessageIds = new Set()
  const messageChangeRefresh = createCoalescedCall({
    delayMs: MESSAGE_CHANGE_COALESCE_MS,
    call: () => refreshChangedMessages()
  })

  // The inbox row previews the last message, which an edit or a delete may have just changed, open
  // conversation or not. A reaction never shows there.
  const inboxRefresh = createCoalescedCall({
    delayMs: MESSAGE_CHANGE_COALESCE_MS,
    call: () => loadThreads({ silent: true })
  })

  function handleMessageChanged({ threadId, messageId, change }) {
    if (hasLoadedThreads.value && change !== 'reaction') {
      inboxRefresh.request()
    }
    if (
      !threadId ||
      threadId !== currentThreadId.value ||
      !isShownOnScreen(messages.value, messageId)
    ) {
      return
    }
    changedMessageIds.add(messageId)
    messageChangeRefresh.request()
  }

  /**
   * Re-reads each changed message through the window around it, which works in a window up the
   * history too (#1380), and swaps in the copies of what the pane holds. One window covers its
   * neighbours, so a burst of changes close together is one request.
   */
  async function refreshChangedMessages() {
    const threadId = currentThreadId.value
    const messageIds = [...changedMessageIds]
    changedMessageIds.clear()

    const refreshed = new Set()
    for (const messageId of messageIds) {
      if (refreshed.has(messageId) || !isShownOnScreen(messages.value, messageId)) continue
      try {
        const page = await messageApi.getMessageWindow(threadId, { around: messageId })
        if (threadId !== currentThreadId.value) return
        const fetched = page.messages ?? []
        for (const message of fetched) refreshed.add(message.id)
        messages.value = withRefreshedHeld(
          messages.value,
          fetched,
          reactionToggle.pendingMessageIds()
        )
        refreshQuotesOf(fetched)
      } catch (e) {
        // The next load puts it right; a live refresh never turns a readable pane into an error.
        console.error('Failed to refresh a changed message:', e)
      }
    }
  }

  function getOtherParticipant(threadMeta) {
    const securityStore = useUserSecurityStore()
    const currentUsername = securityStore.user?.username

    const participants = threadMeta.thread.message_participants || []
    const other = participants.find((p) => p.participant.username !== currentUsername)

    return other?.participant || null
  }

  /**
   * A message landed somewhere in this user's threads (#989).
   *
   * The signal carries a thread id and nothing else, so what is on screen is refetched from the API
   * rather than patched from the payload: no message content travels over the hub.
   */
  async function handleIncomingMessage(threadId) {
    const openThreadId = currentThreadId.value
    const plan = messageSignalPlan({
      inboxLoaded: hasLoadedThreads.value,
      signalThreadId: threadId,
      openThreadId,
      tabVisible: globalThis.document?.visibilityState === 'visible'
    })

    if (!plan.refreshInbox) {
      return
    }
    // Silent: the user did not ask for this refresh and the panes should not blink.
    await loadThreads({ silent: true })

    if (!plan.refreshOpenThread) {
      return
    }

    // An open channel is the band chat's to refetch and mark read (#1244). The row only follows it,
    // here rather than after that read lands, or the count just fetched would show for a moment.
    const openThread = threads.value.find((t) => t.thread.id === openThreadId)
    if (isChannel(openThread)) {
      if (plan.markRead) openThread.unread_count = 0
      return
    }

    // A window up the history: today's page has nowhere to go, the way down just grows (#1380).
    if (!isAtTail.value) {
      windowHasNewer.value = true
      return
    }

    await loadMessages(openThreadId, { silent: true })
    // Their message has landed, so they are no longer writing it. Not on a reconnect, which resyncs
    // the same newest message and would hide somebody still writing the next one.
    if (threadId !== null) typists.forget(messages.value.at(-1)?.author?.id)

    const threadMeta = plan.markRead
      ? threads.value.find((t) => t.thread.id === openThreadId)
      : null
    if (threadMeta && threadMeta.unread_count > 0) {
      await markAsRead(threadMeta.id)
    }
  }

  // --- « X écrit… » in a direct conversation (#1242) ------------------------------------------------

  // The other participant while they write, as a list so the band chat's line reads it unchanged.
  const currentTypists = ref([])
  const typists = createTypistTracker({
    onChange: (userIds) => {
      currentTypists.value = userIds
    }
  })

  function handleTyping({ threadId, userId }) {
    if (threadId && threadId === currentThreadId.value) typists.seen(userId)
  }

  // Best effort: a lost signal only means nobody sees « écrit… » for three seconds.
  const typingNotifier = createTypingNotifier({
    send: () => {
      const threadId = currentThreadId.value
      if (!threadId) return false
      messageApi.sendTyping(threadId).catch(() => {})
      return true
    }
  })

  function notifyTyping() {
    typingNotifier.notify()
  }

  function forgetTyping() {
    typists.clear()
    typingNotifier.reset()
  }

  // --- « En ligne » in a direct conversation (#1246) ----------------------------------------------

  // The other participant while they have the conversation open, as a list like the band chat's.
  // Thread.vue runs the heartbeat, since it owns the page's lifecycle; the store holds the answer.
  const onlineUserIds = ref([])
  // One per page load: the server keeps somebody online while any of their tabs still beats.
  const presenceTab = crypto.randomUUID()
  // The conversation this page is beating for, null once it has left, so that a tab hidden past its
  // grace is not put back online by the other person's arrival.
  let presentInThreadId = null

  async function beatPresence(threadId) {
    presentInThreadId = threadId
    try {
      const online = await messageApi.beatPresence(threadId, presenceTab)
      if (presentInThreadId === threadId) onlineUserIds.value = online
    } catch (e) {
      if (!PRESENCE_REFUSED_STATUSES.includes(e?.status)) {
        // Anything else leaves the header as it was; the next heartbeat puts it right.
        console.error('Failed to send the conversation presence heartbeat:', e)
      } else if (presentInThreadId === threadId) {
        onlineUserIds.value = []
      }
    }
  }

  function leavePresence(threadId) {
    messageApi.leavePresence(threadId, presenceTab)
    if (presentInThreadId === threadId) presentInThreadId = null
    onlineUserIds.value = []
  }

  // The other person's arrival and a second tab of theirs can land together; one heartbeat answers both.
  const presenceRefresh = createCoalescedCall({
    delayMs: 500,
    call: () => {
      if (presentInThreadId) beatPresence(presentInThreadId)
    }
  })

  function handlePresence(threadId) {
    if (threadId && threadId === presentInThreadId) presenceRefresh.request()
  }

  /**
   * The other person read this conversation (#1243), so a « Vu » on screen may be stale. Refetched
   * quietly and never marked read in return: answering a read with a read is how two tabs feed each
   * other.
   */
  function handleThreadRead(threadId) {
    if (threadId && threadId === currentThreadId.value) {
      loadMessages(threadId, { silent: true })
    }
  }

  function forgetMessageState() {
    forgetTyping()
    presenceRefresh.cancel()
    onlineUserIds.value = []
    reactionError.value = null
    reactionToggle.reset()
    changedMessageIds.clear()
    messageChangeRefresh.cancel()
    replyTarget.value = null
    jumpError.value = null
    loadNewerError.value = null
    focusRequest.value = null
    resetWindow()
  }

  function clearCurrentThread() {
    forgetMessageState()
    currentThreadId.value = null
    currentThreadMetaId.value = null
    messages.value = []
    totalMessages.value = 0
    loadOlderError.value = null
  }

  function reset() {
    forgetMessageState()
    inboxRefresh.cancel()
    threads.value = []
    messages.value = []
    currentThreadId.value = null
    currentThreadMetaId.value = null
  }

  return {
    threads: readonly(threads),
    messages: readonly(messages),
    currentThreadId: readonly(currentThreadId),
    currentThreadMetaId: readonly(currentThreadMetaId),
    isLoading: readonly(isLoading),
    hasLoadedThreads: readonly(hasLoadedThreads),
    isLoadingMessages: readonly(isLoadingMessages),
    isLoadingOlderMessages: readonly(isLoadingOlderMessages),
    loadOlderError: readonly(loadOlderError),
    totalMessages: readonly(totalMessages),
    hasOlderMessages,
    hasNewerMessages,
    isAtTail: readonly(isAtTail),
    isLoadingNewerMessages: readonly(isLoadingNewerMessages),
    loadNewerError: readonly(loadNewerError),
    jumpError: readonly(jumpError),
    focusRequest: readonly(focusRequest),
    replyTarget: readonly(replyTarget),
    isAddingMessage: readonly(isAddingMessage),
    reactionError: readonly(reactionError),
    orderedThreads,
    currentThread,
    loadThreads,
    selectThread,
    postMessage,
    postMessageInThread,
    loadOlderMessages,
    loadNewerMessages,
    jumpToMessage,
    dismissJumpError,
    returnToLatest,
    startReply,
    cancelReply,
    handleIncomingMessage,
    handleTyping,
    handleThreadRead,
    handleMessageChanged,
    handlePresence,
    beatPresence,
    leavePresence,
    onlineUserIds: readonly(onlineUserIds),
    editMessage,
    deleteMessage,
    toggleReaction,
    notifyTyping,
    currentTypists: readonly(currentTypists),
    getOtherParticipant,
    clearCurrentThread,
    reset
  }
})
