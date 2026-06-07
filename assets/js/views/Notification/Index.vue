<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Notifications</h1>
      <Button
        v-if="store.pageItems.length > 0"
        label="Tout marquer comme lu"
        link
        size="small"
        :disabled="store.unreadCount === 0"
        @click="store.markAllRead()"
      />
    </div>

    <div v-if="store.pageIsLoading && store.pageItems.length === 0" class="space-y-3">
      <div
        v-for="i in 5"
        :key="i"
        class="h-16 bg-surface-100 dark:bg-surface-800 animate-pulse rounded"
      />
    </div>

    <div
      v-else-if="store.pageItems.length === 0"
      class="text-surface-500 dark:text-surface-400 py-12 text-center"
    >
      Vous n'avez aucune notification pour le moment.
    </div>

    <div v-else>
      <div class="flex flex-col">
        <NotificationItem
          v-for="notification in store.pageItems"
          :key="notification.id"
          :notification="notification"
        />
      </div>

      <div v-if="store.pageTotalItems > ITEMS_PER_PAGE" class="flex justify-center mt-6">
        <Paginator
          :rows="ITEMS_PER_PAGE"
          :totalRecords="store.pageTotalItems"
          :first="(currentPage - 1) * ITEMS_PER_PAGE"
          @page="handlePageChange"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Paginator from 'primevue/paginator'
import { computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NotificationItem from '../../components/Notification/NotificationItem.vue'
import { useUserNotificationStore } from '../../store/notification/userNotification.js'

const ITEMS_PER_PAGE = 20

const route = useRoute()
const router = useRouter()
const store = useUserNotificationStore()

useTitle('Notifications - MusicAll')

const currentPage = computed(() => {
  const parsed = Number.parseInt(route.query.page, 10)
  return Number.isFinite(parsed) && parsed >= 1 ? parsed : 1
})

function handlePageChange(event) {
  const newPage = event.page + 1
  router.push({
    name: 'app_notifications_index',
    query: newPage === 1 ? {} : { page: newPage }
  })
}

watch(currentPage, (page) => {
  store.loadPage(page)
})

onMounted(() => {
  store.loadPage(currentPage.value)
})

onUnmounted(() => {
  store.clearPage()
})
</script>
