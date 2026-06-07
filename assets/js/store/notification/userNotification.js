import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import userNotificationApi from '../../api/notification/userNotification.js'

export const useUserNotificationStore = defineStore('userNotification', () => {
  // Bell dropdown feed (latest page only).
  const items = ref([])
  const unreadCount = ref(0)
  const isLoading = ref(false)

  // Full notifications page (#719): an independent paginated slice so it never clobbers the bell feed
  // (loadFeed overwrites `items` on every popover open).
  const pageItems = ref([])
  const pageTotalItems = ref(0)
  const pageIsLoading = ref(false)

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

  async function loadPage(page) {
    pageIsLoading.value = true
    try {
      const { items: feedItems, total } = await userNotificationApi.getFeed(page)
      pageItems.value = feedItems
      pageTotalItems.value = total
    } catch (e) {
      console.error('Failed to load notifications page:', e)
    } finally {
      pageIsLoading.value = false
    }
  }

  async function loadCount() {
    try {
      unreadCount.value = await userNotificationApi.getCount()
    } catch (e) {
      console.error('Failed to load notifications count:', e)
    }
  }

  // The same notification may live in both the bell feed and the page list (distinct objects);
  // mark whichever copies are loaded, and decrement the unread count once.
  async function markRead(id) {
    const inFeed = items.value.find((n) => n.id === id)
    const inPage = pageItems.value.find((n) => n.id === id)
    if ((inFeed ?? inPage)?.read_datetime) {
      return
    }
    try {
      await userNotificationApi.markRead(id)
      const now = new Date().toISOString()
      if (inFeed) {
        inFeed.read_datetime = now
      }
      if (inPage) {
        inPage.read_datetime = now
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
      for (const notification of [...items.value, ...pageItems.value]) {
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
    for (const notification of [...items.value, ...pageItems.value]) {
      if (notification.payload?.invitation_token === token) {
        notification.payload.invitation_status = outcome
      }
    }
  }

  function clearPage() {
    pageItems.value = []
    pageTotalItems.value = 0
  }

  function clear() {
    items.value = []
    unreadCount.value = 0
    clearPage()
  }

  return {
    items: readonly(items),
    unreadCount: readonly(unreadCount),
    isLoading: readonly(isLoading),
    pageItems: readonly(pageItems),
    pageTotalItems: readonly(pageTotalItems),
    pageIsLoading: readonly(pageIsLoading),
    loadFeed,
    loadPage,
    loadCount,
    markRead,
    markAllRead,
    recordInvitationAction,
    clearPage,
    clear
  }
})
