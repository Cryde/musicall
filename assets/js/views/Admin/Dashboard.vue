<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">Tableau de bord</h1>

    <PendingActionsPanel />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <AdminModuleCard
        v-for="module in ADMIN_MODULES"
        :key="module.key"
        :label="module.label"
        :description="module.description"
        :icon="module.icon"
        :color="module.color"
        :route="module.route"
        :badge-count="badgeCountFor(module)"
      />
    </div>
  </div>
</template>

<script setup>
import AdminModuleCard from '../../components/Admin/AdminModuleCard.vue'
import PendingActionsPanel from '../../components/Admin/PendingActionsPanel.vue'
import { ADMIN_MODULES } from '../../constants/admin.js'
import { useNotificationStore } from '../../store/notification/notification.js'

const notificationStore = useNotificationStore()

function badgeCountFor(module) {
  if (module.key === 'publications') {
    return notificationStore.pendingPublications + notificationStore.pendingGalleries
  }
  return 0
}
</script>
