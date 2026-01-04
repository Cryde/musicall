<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Administration</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- Publications Section -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-file-edit text-primary" />
            <span>Publications</span>
          </div>
        </template>
        <template #content>
          <div class="flex flex-col gap-3">
            <RouterLink
              :to="{ name: 'admin_publications_pending' }"
              class="flex items-center justify-between p-3 rounded-lg bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors"
            >
              <span>Publications en attente</span>
              <Badge v-if="notificationStore.pendingPublications > 0" :value="notificationStore.pendingPublications" severity="warn" />
            </RouterLink>
          </div>
        </template>
      </Card>

      <!-- Galleries Section -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-images text-primary" />
            <span>Galeries</span>
          </div>
        </template>
        <template #content>
          <div class="flex flex-col gap-3">
            <div
              class="flex items-center justify-between p-3 rounded-lg bg-surface-100 dark:bg-surface-800 opacity-50 cursor-not-allowed"
            >
              <span>Galeries en attente</span>
              <Badge v-if="notificationStore.pendingGalleries > 0" :value="notificationStore.pendingGalleries" severity="warn" />
            </div>
          </div>
        </template>
      </Card>

      <!-- Users Section -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-users text-primary" />
            <span>Utilisateurs</span>
          </div>
        </template>
        <template #content>
          <div class="flex flex-col gap-3">
            <div
              class="flex items-center justify-between p-3 rounded-lg bg-surface-100 dark:bg-surface-800 opacity-50 cursor-not-allowed"
            >
              <span>Gestion des membres</span>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import Badge from 'primevue/badge'
import Card from 'primevue/card'
import { onMounted } from 'vue'
import { useNotificationStore } from '../../store/notification/notification.js'

const notificationStore = useNotificationStore()

onMounted(async () => {
  await notificationStore.loadNotifications()
})
</script>
