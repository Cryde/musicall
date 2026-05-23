<template>
  <DashboardWidget
    title="Tâches"
    icon="pi pi-check-square"
    :is-loading="isLoading"
    :error="error"
    :is-empty="!isLoading && !error && totalOpen === 0 && stats?.done === 0"
    empty-message="Aucune tâche pour le moment."
  >
    <template #header-action>
      <RouterLink
        :to="{ name: 'app_band_tasks', params: { id: bandSpaceId } }"
        class="text-xs text-primary hover:underline"
      >
        Voir le board
      </RouterLink>
    </template>

    <div v-if="stats" class="flex flex-col gap-3">
      <div class="grid grid-cols-3 gap-2">
        <div class="bg-surface-100 dark:bg-surface-800 rounded-lg p-3 text-center">
          <div class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ stats.todo }}</div>
          <div class="text-xs text-surface-500 mt-1">À faire</div>
        </div>
        <div class="bg-surface-100 dark:bg-surface-800 rounded-lg p-3 text-center">
          <div class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ stats.in_progress }}</div>
          <div class="text-xs text-surface-500 mt-1">En cours</div>
        </div>
        <div class="bg-surface-100 dark:bg-surface-800 rounded-lg p-3 text-center">
          <div class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ stats.done }}</div>
          <div class="text-xs text-surface-500 mt-1">Terminé</div>
        </div>
      </div>
      <RouterLink
        v-if="stats.overdue > 0"
        :to="{ name: 'app_band_tasks', params: { id: bandSpaceId }, query: { overdue: 1 } }"
        class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-950 rounded-lg hover:bg-red-100 dark:hover:bg-red-900 transition-colors"
      >
        <span class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300 font-medium">
          <i class="pi pi-exclamation-triangle" aria-hidden="true" />
          {{ stats.overdue }} en retard
        </span>
        <i class="pi pi-arrow-right text-red-700 dark:text-red-300 text-sm" aria-hidden="true" />
      </RouterLink>
    </div>
  </DashboardWidget>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import DashboardWidget from './DashboardWidget.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const stats = ref(null)
const isLoading = ref(true)
const error = ref(null)

const totalOpen = computed(() => (stats.value?.todo ?? 0) + (stats.value?.in_progress ?? 0))

onMounted(async () => {
  try {
    stats.value = await bandSpaceTasksApi.getStats(props.bandSpaceId)
  } catch {
    error.value = 'Impossible de charger les tâches.'
  } finally {
    isLoading.value = false
  }
})
</script>
