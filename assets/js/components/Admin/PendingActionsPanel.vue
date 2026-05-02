<template>
  <div
    v-if="visibleItems.length > 0"
    class="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-2xl p-5"
  >
    <div class="flex items-center gap-2 mb-3">
      <i class="pi pi-exclamation-triangle text-amber-500" />
      <h2 class="text-base font-semibold text-amber-900 dark:text-amber-200">À traiter</h2>
    </div>

    <div class="flex flex-col gap-2">
      <RouterLink
        v-for="item in visibleItems"
        :key="item.key"
        :to="{ name: item.route }"
        class="flex items-center justify-between px-3 py-2 rounded-lg bg-surface-0 dark:bg-surface-900 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
      >
        <div class="flex items-center gap-3">
          <i :class="['pi', item.icon, 'text-amber-500']" />
          <span class="font-medium">{{ item.label }}</span>
        </div>
        <div class="flex items-center gap-2">
          <Badge :value="item.count" severity="warn" />
          <i class="pi pi-arrow-right text-surface-400" />
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import Badge from 'primevue/badge'
import { computed } from 'vue'
import { useNotificationStore } from '../../store/notification/notification.js'

const notificationStore = useNotificationStore()

const items = computed(() => [
  {
    key: 'pending-publications',
    label: 'Publications en attente',
    icon: 'pi-file-edit',
    route: 'admin_publications_pending',
    count: notificationStore.pendingPublications
  },
  {
    key: 'pending-galleries',
    label: 'Galeries en attente',
    icon: 'pi-images',
    route: 'admin_galleries_pending',
    count: notificationStore.pendingGalleries
  }
])

const visibleItems = computed(() => items.value.filter((item) => item.count > 0))
</script>
