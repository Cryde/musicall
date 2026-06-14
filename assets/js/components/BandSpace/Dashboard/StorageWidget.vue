<template>
  <DashboardWidget
    title="Stockage"
    icon="pi pi-database"
    :is-loading="isLoading"
    :error="error"
  >
    <template #header-action>
      <RouterLink
        :to="{ name: 'app_band_files', params: { id: bandSpaceId } }"
        class="text-xs text-primary hover:underline"
      >
        Voir les fichiers
      </RouterLink>
    </template>

    <div v-if="quota" class="flex flex-col gap-3">
      <ProgressBar
        :value="cappedPercentage"
        :show-value="false"
        aria-label="Stockage utilisé"
        :pt="{ value: { class: progressBarClass } }"
      />
      <div class="flex items-center justify-between text-sm">
        <span class="text-surface-700 dark:text-surface-200">
          {{ formatBytes(quota.used_bytes) }} / {{ formatBytes(quota.quota_bytes) }}
        </span>
        <span :class="percentageClass" class="text-xs tabular-nums font-medium">
          {{ Math.round(quota.used_percentage) }}%
        </span>
      </div>
    </div>
  </DashboardWidget>
</template>

<script setup>
import ProgressBar from 'primevue/progressbar'
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { formatBytes } from '../../../utils/formatBytes.js'
import DashboardWidget from './DashboardWidget.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const quota = ref(null)
const isLoading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    quota.value = await bandSpaceFilesApi.getQuota(props.bandSpaceId)
  } catch {
    error.value = 'Quota indisponible.'
  } finally {
    isLoading.value = false
  }
})

const cappedPercentage = computed(() => Math.min(100, quota.value?.used_percentage ?? 0))

const percentageClass = computed(() => {
  const pct = quota.value?.used_percentage ?? 0
  if (pct >= 100) return 'text-red-500'
  if (pct >= 80) return 'text-amber-500'
  return 'text-surface-500 dark:text-surface-400'
})

const progressBarClass = computed(() => {
  const pct = quota.value?.used_percentage ?? 0
  if (pct >= 100) return '!bg-red-500'
  if (pct >= 80) return '!bg-amber-500'
  return ''
})
</script>
