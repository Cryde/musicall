<template>
  <HomeMemberPanel title="Activité" link-label="Toutes les notifications" :link-to="{ name: 'app_notifications_index' }">
    <div v-if="userNotificationStore.isLoading && items.length === 0" class="flex flex-col gap-3" aria-busy="true">
      <div v-for="i in 2" :key="i" class="h-12 rounded-lg bg-surface-100 dark:bg-surface-800 animate-pulse" />
    </div>
    <div v-else-if="items.length > 0" class="flex flex-col -mx-2">
      <NotificationItem v-for="notification in items" :key="notification.id" :notification="notification" />
    </div>
    <p v-else class="m-0 text-sm text-surface-600 dark:text-surface-300">Rien de neuf pour le moment.</p>
  </HomeMemberPanel>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useUserNotificationStore } from '../../../store/notification/userNotification.js'
import NotificationItem from '../../Notification/NotificationItem.vue'
import HomeMemberPanel from './HomeMemberPanel.vue'

/** The latest notifications (#1078), the bell's own feed and rendering. */
const ITEMS_SHOWN = 3

const userNotificationStore = useUserNotificationStore()
const items = computed(() => userNotificationStore.items.slice(0, ITEMS_SHOWN))

onMounted(() => {
  userNotificationStore.loadFeed()
})
</script>
