import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import messageApi from '../../api/message/message.js'
import { handleApiError } from '../../api/utils/handleApiError.js'
import { isMessageAlreadyListed, messageSignalPlan } from '../../utils/messageSignal.js'
import { useNotificationStore } from '../notification/notification.js'
import { useUserSecurityStore } from '../user/security.js'

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
  const isAddingMessage = ref(false)

  const orderedThreads = computed(() => {
    return [...threads.value].sort((a, b) => {
      const dateA = new Date(a.thread.last_message?.creation_datetime || 0)
      const dateB = new Date(b.thread.last_message?.creation_datetime || 0)
      return dateB - dateA
    })
  })

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
    currentThreadId.value = threadMeta.thread.id
    currentThreadMetaId.value = threadMeta.id

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
      messages.value = (response.member || []).reverse()
    } catch (e) {
      console.error('Failed to load messages:', e)
      if (!silent && currentRequestId === messagesRequestId) {
        messages.value = []
      }
    } finally {
      if (!silent && currentRequestId === messagesRequestId) {
        isLoadingMessages.value = false
      }
    }
  }

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

  async function postMessage({ recipientId, content }) {
    isAddingMessage.value = true
    try {
      const newMessage = await messageApi.postMessage({ recipientId, content })
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
      // The signal for this very message can arrive before this promise resolves, because the server
      // publishes inside the send and answers afterwards. That refetch has already put the message in
      // the list, so pushing it again would show it twice.
      if (!isMessageAlreadyListed(messages.value, newMessage)) {
        messages.value.push(newMessage)
      }

      // Update last message in thread
      const thread = threads.value.find((t) => t.thread.id === threadId)
      if (thread) {
        thread.thread.last_message = newMessage
      }
    } finally {
      isAddingMessage.value = false
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
    await loadMessages(openThreadId, { silent: true })

    const threadMeta = plan.markRead
      ? threads.value.find((t) => t.thread.id === openThreadId)
      : null
    if (threadMeta && threadMeta.unread_count > 0) {
      await markAsRead(threadMeta.id)
    }
  }

  function clearCurrentThread() {
    currentThreadId.value = null
    currentThreadMetaId.value = null
    messages.value = []
  }

  function reset() {
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
    isAddingMessage: readonly(isAddingMessage),
    orderedThreads,
    currentThread,
    loadThreads,
    selectThread,
    postMessage,
    postMessageInThread,
    handleIncomingMessage,
    getOtherParticipant,
    clearCurrentThread,
    reset
  }
})
