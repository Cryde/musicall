import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import notificationApi from '../../api/notification/notification.js'
import { CHAT_TESTER_ONLY } from '../../constants/bandSpace.js'
import { useUserSecurityStore } from '../user/security.js'

export const useNotificationStore = defineStore('notification', () => {
  // As the API reports it, channels included. Read the badge through unreadMessages below.
  const reportedUnreadMessages = ref(0)
  const pendingPublications = ref(0)
  const pendingGalleries = ref(0)
  const newFeedbacks = ref(0)
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
      reportedUnreadMessages.value = data.unread_messages || 0
      pendingPublications.value = data.pending_publications || 0
      pendingGalleries.value = data.pending_galleries || 0
      newFeedbacks.value = data.new_feedbacks || 0
      bandSpaceChatUnread.value = data.band_space_chat_unread || {}
    } catch (e) {
      console.error('Failed to load notifications:', e)
    }
  }

  /**
   * The navbar envelope. Channels are counted by the API again (#994), but the inbox under that
   * envelope only lists them for a tester while CHAT_TESTER_ONLY stands, so for everybody else they
   * come back out here: a badge nobody can click through to explain or clear is worse than no badge,
   * which is the very reason #961 stopped counting them in the first place.
   *
   * Subtracted here rather than filtered server side because ROLE_TESTER is never checked in PHP.
   * Both numbers read one `lastReadDatetime` through the same active membership rule, so today they
   * agree exactly. They are not guaranteed to: only the envelope skips a conversation marked deleted,
   * which nothing sets on a channel yet. The floor is for the day something does, because a negative
   * badge would be a worse way to find that out than a missing one.
   */
  const unreadMessages = computed(() => {
    if (!CHAT_TESTER_ONLY || useUserSecurityStore().isTester) {
      return reportedUnreadMessages.value
    }

    const inChannels = Object.values(bandSpaceChatUnread.value).reduce(
      (total, count) => total + count,
      0
    )

    return Math.max(reportedUnreadMessages.value - inChannels, 0)
  })

  function chatUnreadFor(bandSpaceId) {
    return bandSpaceChatUnread.value[bandSpaceId] ?? 0
  }

  return {
    unreadMessages,
    bandSpaceChatUnread: readonly(bandSpaceChatUnread),
    chatUnreadFor,
    pendingPublications: readonly(pendingPublications),
    pendingGalleries: readonly(pendingGalleries),
    newFeedbacks: readonly(newFeedbacks),
    loadNotifications
  }
})
