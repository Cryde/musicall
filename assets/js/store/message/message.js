import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import messageApi from '../../api/message/message.js'
import { handleApiError } from '../../api/utils/handleApiError.js'
import { CHAT_TESTER_ONLY } from '../../constants/bandSpace.js'
import { i18n } from '../../i18n/index.js'
import { createReactionToggle } from '../../utils/chatReactionToggle.js'
import { createTypingNotifier, createTypistTracker } from '../../utils/chatTyping.js'
import { isHeld, withRefreshedHeld } from '../../utils/chatWindow.js'
import { createCoalescedCall } from '../../utils/coalescedCall.js'
import { isChannel } from '../../utils/conversationIdentity.js'
import {
  hasOlderToLoad,
  mergeMessages,
  nextOlderPageToLoad,
  pageHoldingMessage
} from '../../utils/messagePagination.js'
import { isMessageAlreadyListed, messageSignalPlan } from '../../utils/messageSignal.js'
import { useNotificationStore } from '../notification/notification.js'
import { useUserSecurityStore } from '../user/security.js'

/** Changes signalled within this window are re-read together, as in the band chat (#1056). */
const MESSAGE_CHANGE_COALESCE_MS = 1000

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

  /**
   * Newest conversation first, and a Band Space channel only for a tester while CHAT_TESTER_ONLY
   * stands (#994).
   *
   * The curtain is drawn here rather than in PHP, like the chat tab's route guard and the band
   * sidebar's filter: ROLE_TESTER is a feature flag and not a rank, and nothing on the server has
   * ever consulted it. The chat API is open either way, which is what makes this a curtain.
   */
  const orderedThreads = computed(() => {
    const hidesChannels = CHAT_TESTER_ONLY && !useUserSecurityStore().isTester

    return [...threads.value]
      .filter((threadMeta) => !hidesChannels || !isChannel(threadMeta))
      .sort((a, b) => {
        const dateA = new Date(a.thread.last_message?.creation_datetime || 0)
        const dateB = new Date(b.thread.last_message?.creation_datetime || 0)
        return dateB - dateA
      })
  })

  const hasOlderMessages = computed(() =>
    hasOlderToLoad(messages.value.length, totalMessages.value)
  )

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
    const currentRequestId = ++messagesRequestId
    if (!silent) {
      isLoadingMessages.value = true
    }
    try {
      const response = await messageApi.getMessages({ threadId })
      if (currentRequestId !== messagesRequestId) return
      // Reverse to show oldest first
      const newest = (response.member || []).reverse()
      // A silent reload is a live signal refetching the newest page, and the reader may have loaded
      // history behind it. Replacing would throw that away mid-read, so merge instead (#989).
      messages.value = silent ? mergeMessages(messages.value, newest) : newest
      totalMessages.value = response.totalItems ?? newest.length
      loadOlderError.value = null
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

  async function postMessageInThread({ threadId, content }) {
    isAddingMessage.value = true
    try {
      const newMessage = await messageApi.postMessageInThread({ threadId, content })
      typingNotifier.reset()
      // The signal for this very message can arrive before this promise resolves, because the server
      // publishes inside the send and answers afterwards. That refetch has already put the message in
      // the list, so pushing it again would show it twice.
      if (!isMessageAlreadyListed(messages.value, newMessage)) {
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
    }
  }

  /** A tombstone in place: the row stays, so nobody's scroll position moves (#967). */
  async function deleteMessage(messageId) {
    const threadId = currentThreadId.value
    await messageApi.deleteMessage({ threadId, messageId })
    if (threadId !== currentThreadId.value) return
    messages.value = messages.value.map((message) =>
      message.id === messageId
        ? { ...message, content: '', is_deleted: true, editable_content: undefined }
        : message
    )
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
    if (!threadId || threadId !== currentThreadId.value || !isHeld(messages.value, messageId)) {
      return
    }
    changedMessageIds.add(messageId)
    messageChangeRefresh.request()
  }

  async function refreshChangedMessages() {
    const threadId = currentThreadId.value
    const pages = new Set(
      [...changedMessageIds].map((messageId) => pageHoldingMessage(messages.value, messageId))
    )
    changedMessageIds.clear()
    pages.delete(null)

    for (const page of pages) {
      try {
        const response = await messageApi.getMessages({ threadId, page })
        if (threadId !== currentThreadId.value) return
        messages.value = withRefreshedHeld(
          messages.value,
          response.member ?? [],
          reactionToggle.pendingMessageIds()
        )
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
    reactionError.value = null
    reactionToggle.reset()
    changedMessageIds.clear()
    messageChangeRefresh.cancel()
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
    isAddingMessage: readonly(isAddingMessage),
    reactionError: readonly(reactionError),
    orderedThreads,
    currentThread,
    loadThreads,
    selectThread,
    postMessage,
    postMessageInThread,
    loadOlderMessages,
    handleIncomingMessage,
    handleTyping,
    handleThreadRead,
    handleMessageChanged,
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
