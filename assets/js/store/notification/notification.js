import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import notificationApi from '../../api/notification/notification.js'

export const useNotificationStore = defineStore('notification', () => {
  const unreadMessages = ref(0)

  async function loadNotifications() {
    try {
      const data = await notificationApi.getNotifications()
      unreadMessages.value = data.unread_messages || 0
    } catch (e) {
      console.error('Failed to load notifications:', e)
    }
  }

  return {
    unreadMessages: readonly(unreadMessages),
    loadNotifications
  }
})
