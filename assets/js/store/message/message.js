import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import messageApi from '../../api/message/message.js'
import { useNotificationStore } from '../notification/notification.js'
import { useUserSecurityStore } from '../user/security.js'

export const useMessageStore = defineStore('message', () => {
  const threads = ref([])
  const messages = ref([])
  const currentThreadId = ref(null)
  const currentThreadMetaId = ref(null)
  const isLoading = ref(false)
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

  const unreadCount = computed(() => {
    return threads.value.filter((t) => !t.is_read).length
  })

  async function loadThreads() {
    isLoading.value = true
    try {
      const response = await messageApi.getThreads()
      threads.value = response.member || []
    } catch (e) {
      console.error('Failed to load threads:', e)
      threads.value = []
    } finally {
      isLoading.value = false
    }
  }

  async function selectThread(threadMeta) {
    currentThreadId.value = threadMeta.thread.id
    currentThreadMetaId.value = threadMeta.id

    await loadMessages(threadMeta.thread.id)

    // Mark as read if unread
    if (!threadMeta.is_read) {
      await markAsRead(threadMeta.id)
    }
  }

  async function loadMessages(threadId) {
    isLoadingMessages.value = true
    try {
      const response = await messageApi.getMessages({ threadId })
      // Reverse to show oldest first
      messages.value = (response.member || []).reverse()
    } catch (e) {
      console.error('Failed to load messages:', e)
      messages.value = []
    } finally {
      isLoadingMessages.value = false
    }
  }

  async function markAsRead(threadMetaId) {
    try {
      await messageApi.markThreadAsRead({ threadMetaId })
      const thread = threads.value.find((t) => t.id === threadMetaId)
      if (thread) {
        thread.is_read = true
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
    } finally {
      isAddingMessage.value = false
    }
  }

  async function postMessageInThread({ threadId, content }) {
    isAddingMessage.value = true
    try {
      const newMessage = await messageApi.postMessageInThread({ threadId, content })
      messages.value.push(newMessage)

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
    isLoadingMessages: readonly(isLoadingMessages),
    isAddingMessage: readonly(isAddingMessage),
    orderedThreads,
    currentThread,
    unreadCount,
    loadThreads,
    selectThread,
    postMessage,
    postMessageInThread,
    getOtherParticipant,
    clearCurrentThread,
    reset
  }
})
