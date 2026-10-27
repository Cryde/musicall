import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import notificationApi from '../../api/notification/notification.js'

export const useNotificationStore = defineStore('notification', () => {
  // The navbar envelope, Band Space channels included (#994).
  const unreadMessages = ref(0)
  const pendingPublications = ref(0)
  const pendingGalleries = ref(0)
  const newFeedbacks = ref(0)
  const pendingReports = ref(0)
  // Keyed by band space id, and a space with nothing unread is absent rather than zero, so read it
  // through chatUnreadFor() rather than indexing the map.
  const bandSpaceChatUnread = ref({})

  // One live signal can ask for this twice, once from the chat store and once from the inbox marking
  // the conversation read, so an older response landing last would put the badge back to the count it
  // had before the read. Same generation guard the message stores use.
  let loadToken = 0

  async function loadNotifications() {
    const token = ++loadToken
    try {
      const data = await notificationApi.getNotifications()
      if (token !== loadToken) {
        return
      }
      unreadMessages.value = data.unread_messages || 0
      pendingPublications.value = data.pending_publications || 0
      pendingGalleries.value = data.pending_galleries || 0
      newFeedbacks.value = data.new_feedbacks || 0
      pendingReports.value = data.pending_reports || 0
      bandSpaceChatUnread.value = data.band_space_chat_unread || {}
    } catch (e) {
      console.error('Failed to load notifications:', e)
    }
  }

  function chatUnreadFor(bandSpaceId) {
    return bandSpaceChatUnread.value[bandSpaceId] ?? 0
  }

  return {
    unreadMessages: readonly(unreadMessages),
    bandSpaceChatUnread: readonly(bandSpaceChatUnread),
    chatUnreadFor,
    pendingPublications: readonly(pendingPublications),
    pendingGalleries: readonly(pendingGalleries),
    newFeedbacks: readonly(newFeedbacks),
    pendingReports: readonly(pendingReports),
    loadNotifications
  }
})
