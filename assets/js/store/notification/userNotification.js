import { defineStore } from 'pinia'
import { readonly, ref, watch } from 'vue'
import userNotificationApi from '../../api/notification/userNotification.js'
import { createNotificationStream, notificationTopic } from '../../utils/notificationStream.js'
import { useUserSecurityStore } from '../user/security.js'

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

  // The live stream. It lives in the store rather than in NotificationBell.vue because the bell
  // unmounts whenever the layout changes (AppBaseLayout to AppBandLayout and back), which would drop
  // and reopen the connection on every entry into a Band Space. A store is a singleton for the page,
  // so whoever mounts first connects and the rest is a no-op.
  const stream = createNotificationStream({
    // A list of one. See notificationStream.js for why it is a list at all.
    getTopics: () => {
      const userId = useUserSecurityStore().userProfile?.id

      return userId ? [notificationTopic(userId)] : []
    },
    onSignal: () => loadCount(),
    onAuthRefreshNeeded: () => useUserSecurityStore().checkAuthInfo()
  })

  // Authentication completes one HTTP round trip before the profile does: checkAuthInfo() sets
  // isAuthenticated and then calls fetchUserProfile() without awaiting it. So a bell that mounts from
  // a warm cache can call connect() while userProfile is still null, find no id, and never open the
  // stream at all, leaving the user on the fallback poll for the whole session with nothing logged.
  // Waiting for the id rather than for the mount is what makes that race unlosable.
  watch(
    () => useUserSecurityStore().userProfile?.id,
    (userId, previousUserId) => {
      // Reconnect rather than connect: connect() refuses to run while a stream is open, and the
      // topics live in that stream's URL. Without the teardown, a session that changed user would
      // keep listening on the old topic and a widened topic list would be silently ignored.
      if (previousUserId) {
        stream.disconnect()
      }
      if (userId) {
        stream.connect()
      }
    },
    { immediate: true }
  )

  function clear() {
    stream.disconnect()
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
    connect: stream.connect,
    disconnect: stream.disconnect,
    clearPage,
    clear
  }
})
