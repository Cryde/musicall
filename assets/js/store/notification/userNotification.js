import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import userNotificationApi from '../../api/notification/userNotification.js'

export const useUserNotificationStore = defineStore('userNotification', () => {
  const items = ref([])
  const unreadCount = ref(0)
  const isLoading = ref(false)

  async function loadFeed() {
    isLoading.value = true
    try {
      const { items: feedItems } = await userNotificationApi.getFeed()
      items.value = feedItems
    } catch (e) {
      console.error('Failed to load notifications feed:', e)
    } finally {
      isLoading.value = false
    }
  }

  async function loadCount() {
    try {
      unreadCount.value = await userNotificationApi.getCount()
    } catch (e) {
      console.error('Failed to load notifications count:', e)
    }
  }

  async function markRead(id) {
    const notification = items.value.find((n) => n.id === id)
    if (notification?.read_datetime) {
      return
    }
    try {
      await userNotificationApi.markRead(id)
      if (notification) {
        notification.read_datetime = new Date().toISOString()
      }
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (e) {
      console.error('Failed to mark notification read:', e)
    }
  }

  async function markAllRead() {
    try {
      await userNotificationApi.markAllRead()
      const now = new Date().toISOString()
      for (const notification of items.value) {
        if (notification.read_datetime === null) {
          notification.read_datetime = now
        }
      }
      unreadCount.value = 0
    } catch (e) {
      console.error('Failed to mark all notifications read:', e)
    }
  }

  // Optimistically reflect an invitation action on the loaded item so its buttons swap
  // to a resolved state without waiting for a feed reload. The server is authoritative:
  // `payload.invitation_status` is recomputed live on the next load (see the feed enricher).
  function recordInvitationAction(token, outcome) {
    for (const notification of items.value) {
      if (notification.payload?.invitation_token === token) {
        notification.payload.invitation_status = outcome
      }
    }
  }

  function clear() {
    items.value = []
    unreadCount.value = 0
  }

  return {
    items: readonly(items),
    unreadCount: readonly(unreadCount),
    isLoading: readonly(isLoading),
    loadFeed,
    loadCount,
    markRead,
    markAllRead,
    recordInvitationAction,
    clear
  }
})
