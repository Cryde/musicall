<template>
  <div class="flex flex-col lg:flex-row gap-4 lg:gap-6">
    <aside class="lg:w-64 shrink-0">
      <AdminSidebar />
    </aside>

    <main class="flex-1 min-w-0">
      <RouterView />
    </main>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useNotificationStore } from '../store/notification/notification.js'
import AdminSidebar from './Admin/AdminSidebar.vue'

const notificationStore = useNotificationStore()

onMounted(() => {
  notificationStore.loadNotifications()
})
</script>
