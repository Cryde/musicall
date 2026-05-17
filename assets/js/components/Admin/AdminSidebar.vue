<template>
  <nav class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-3">
    <div class="flex flex-row lg:flex-col gap-1 overflow-x-auto lg:overflow-x-visible">
      <RouterLink
        :to="{ name: 'admin_dashboard' }"
        :class="[
          'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm font-medium whitespace-nowrap shrink-0 lg:shrink',
          isActive('admin_dashboard')
            ? 'bg-primary text-primary-contrast'
            : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'
        ]"
      >
        <i class="pi pi-chart-bar" />
        <span>Tableau de bord</span>
      </RouterLink>

      <RouterLink
        v-for="module in ADMIN_MODULES"
        :key="module.key"
        :to="{ name: module.route }"
        :class="[
          'flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm font-medium whitespace-nowrap shrink-0 lg:shrink',
          isModuleActive(module)
            ? 'bg-primary text-primary-contrast'
            : 'text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800'
        ]"
      >
        <i :class="['pi', module.icon]" />
        <span class="flex-1">{{ module.label }}</span>
        <Badge
          v-if="module.key === 'publications' && publicationsBadgeCount > 0"
          :value="publicationsBadgeCount"
          severity="warn"
        />
      </RouterLink>
    </div>
  </nav>
</template>

<script setup>
import Badge from 'primevue/badge'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { ADMIN_MODULES } from '../../constants/admin.js'
import { useNotificationStore } from '../../store/notification/notification.js'

const route = useRoute()
const notificationStore = useNotificationStore()

const publicationsBadgeCount = computed(
  () => notificationStore.pendingPublications + notificationStore.pendingGalleries
)

const PUBLICATIONS_ROUTES = new Set([
  'admin_publications_index',
  'admin_publications_pending',
  'admin_publications_tags',
  'admin_galleries_pending'
])

function isActive(routeName) {
  return route.name === routeName
}

function isModuleActive(module) {
  if (module.key === 'publications') {
    return PUBLICATIONS_ROUTES.has(route.name)
  }
  return route.name === module.route
}
</script>
