import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceChatApi from '../../api/bandSpace/band-space-chat.js'
import {
  hasOlderToLoad,
  mergeMessages,
  nextOlderPageToLoad
} from '../../utils/messagePagination.js'
import { useNotificationStore } from '../notification/notification.js'

/**
 * The band's conversation. Held oldest first, which is reading order, while the API answers newest
 * first so that page 1 is the newest page: every page is reversed on arrival.
 *
 * The pagination helpers are the ones the direct message thread already uses (#955). They key on
 * `@id` and `creation_datetime`, both of which a chat message carries, so nothing there needed
 * widening.
 */
export const useBandSpaceChatStore = defineStore('bandSpaceChat', () => {
  const messages = ref([])
  const totalMessages = ref(0)
  const isLoading = ref(false)
  const loadError = ref(null)
  const isLoadingOlder = ref(false)
  const loadOlderError = ref(null)
  const isSending = ref(false)

  const hasOlderMessages = computed(() =>
    hasOlderToLoad(messages.value.length, totalMessages.value)
  )

  /**
   * The total can only go up, because nothing deletes a message yet. A response that was issued
   * before a send lands with a total that predates it, and taking it at face value would hide the
   * « charger les messages plus anciens » button while history is still unread. Revisit at #967,
   * which is when a message can start disappearing.
   */
  function knownTotal(reported) {
    return Math.max(reported ?? 0, totalMessages.value, messages.value.length)
  }

  // Compared after every await: switching space remounts the view, and a slow first page must not
  // land on top of the space the member has already moved to.
  let loadToken = 0

  async function loadMessages(bandSpaceId) {
    const token = ++loadToken
    isLoading.value = true
    loadError.value = null
    loadOlderError.value = null

    try {
      const response = await bandSpaceChatApi.getMessages(bandSpaceId)
      if (token !== loadToken) {
        return
      }
      // Merged, not replaced: the composer is usable while this is in flight, and a message sent
      // meanwhile would otherwise be wiped from the pane by a response that predates it. The store
      // is cleared when the view mounts, so on a normal load there is nothing to merge with.
      messages.value = mergeMessages(messages.value, (response.member || []).reverse())
      totalMessages.value = knownTotal(response.totalItems)
    } catch (e) {
      console.error('Failed to load the chat:', e)
      if (token === loadToken) {
        messages.value = []
        totalMessages.value = 0
        loadError.value = 'Impossible de charger la discussion'
      }
    } finally {
      if (token === loadToken) {
        isLoading.value = false
      }
    }
  }

  /**
   * Prepends the next page of history. The page to ask for is derived from how many messages are
   * held rather than from a counter, so it cannot drift.
   */
  async function loadOlderMessages(bandSpaceId) {
    if (isLoadingOlder.value || !hasOlderMessages.value) {
      return
    }

    const token = loadToken
    isLoadingOlder.value = true
    loadOlderError.value = null

    try {
      const response = await bandSpaceChatApi.getMessages(bandSpaceId, {
        page: nextOlderPageToLoad(messages.value.length)
      })
      if (token !== loadToken) {
        return
      }
      messages.value = mergeMessages(messages.value, (response.member || []).reverse())
      totalMessages.value = knownTotal(response.totalItems)
    } catch (e) {
      console.error('Failed to load older chat messages:', e)
      loadOlderError.value = 'Impossible de charger les messages plus anciens.'
    } finally {
      isLoadingOlder.value = false
    }
  }

  /**
   * Appends through the merge rather than pushing, so the message is de-duplicated by `@id` the day
   * #963 makes the same message arrive over Mercure as well.
   */
  async function sendMessage(bandSpaceId, content) {
    isSending.value = true

    try {
      const message = await bandSpaceChatApi.postMessage(bandSpaceId, content)
      const heldBefore = messages.value.length
      messages.value = mergeMessages(messages.value, [message])
      totalMessages.value += messages.value.length - heldBefore
    } finally {
      isSending.value = false
    }
  }

  /**
   * Opening the tab is reading it. The badge lives on the notification payload rather than in this
   * store, because the sidebar shows it from every other module too, so clearing it means refreshing
   * that payload, the same shape the direct message store uses after marking a thread read.
   */
  async function markAsRead(bandSpaceId) {
    try {
      await bandSpaceChatApi.markAsRead(bandSpaceId)
      await useNotificationStore().loadNotifications()
    } catch (e) {
      console.error('Failed to mark the chat as read:', e)
    }
  }

  function clear() {
    messages.value = []
    totalMessages.value = 0
    isLoading.value = false
    loadError.value = null
    isLoadingOlder.value = false
    loadOlderError.value = null
    isSending.value = false
  }

  return {
    messages: readonly(messages),
    totalMessages: readonly(totalMessages),
    isLoading: readonly(isLoading),
    loadError: readonly(loadError),
    isLoadingOlder: readonly(isLoadingOlder),
    loadOlderError: readonly(loadOlderError),
    isSending: readonly(isSending),
    hasOlderMessages,
    loadMessages,
    loadOlderMessages,
    sendMessage,
    markAsRead,
    clear
  }
})
