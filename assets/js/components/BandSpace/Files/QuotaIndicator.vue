<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-surface-800 dark:text-surface-100">Stockage</h2>
      <RouterLink
        :to="{ name: 'app_band_files', params: { id: bandSpaceId } }"
        class="text-sm text-primary-500 hover:underline flex items-center gap-1"
      >
        Voir les fichiers
        <i class="pi pi-arrow-right text-xs"></i>
      </RouterLink>
    </div>

    <div v-if="filesStore.isLoadingQuota && !quota" class="flex flex-col gap-3">
      <Skeleton width="100%" height="1.5rem" borderRadius="0.5rem" />
      <Skeleton width="50%" height="1rem" />
      <Skeleton width="100%" height="0.75rem" borderRadius="9999px" />
    </div>

    <div v-else-if="!quota" class="text-sm italic text-surface-400 text-center py-8">
      Indisponible.
    </div>

    <div v-else class="flex flex-col gap-4">
      <div class="flex flex-col gap-2">
        <ProgressBar
          :value="cappedPercentage"
          :show-value="false"
          :pt="{
            value: { class: progressBarClass }
          }"
        />
        <div class="flex items-center justify-between text-sm">
          <span class="font-medium text-surface-800 dark:text-surface-100">
            {{ formatBytes(quota.used_bytes) }} sur {{ formatBytes(quota.quota_bytes) }} utilisés
          </span>
          <span
            v-tooltip.top="quotaHint"
            class="flex items-center gap-1 text-xs tabular-nums"
            :class="percentageClass"
          >
            {{ formatPercentage(quota.used_percentage) }}
            <i class="pi pi-info-circle text-surface-400"></i>
          </span>
        </div>
      </div>

      <div v-if="totalBreakdownBytes > 0" class="flex flex-col gap-2">
        <div class="flex h-3 w-full rounded-full overflow-hidden bg-surface-100 dark:bg-surface-800">
          <div
            v-for="seg in segments"
            :key="seg.key"
            :style="{ width: `${seg.widthPct}%`, backgroundColor: seg.color }"
            v-tooltip.top="`${seg.label} : ${formatBytes(seg.bytes)}`"
          />
        </div>

        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs">
          <div
            v-for="seg in segments"
            :key="seg.key"
            class="flex items-center gap-2 text-surface-600 dark:text-surface-300"
          >
            <span
              class="inline-block w-2.5 h-2.5 rounded-sm"
              :style="{ backgroundColor: seg.color }"
            />
            <span class="font-medium">{{ seg.label }}</span>
            <span class="tabular-nums text-surface-400">
              {{ formatBytes(seg.bytes) }} ({{ formatPercentage(seg.pct) }})
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import ProgressBar from 'primevue/progressbar'
import Skeleton from 'primevue/skeleton'
import { computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { formatBytes } from '../../../utils/formatBytes.js'

const route = useRoute()
const filesStore = useBandFilesStore()

const bandSpaceId = computed(() => route.params.id)

const quota = computed(() => filesStore.quota)

const cappedPercentage = computed(() => {
  const pct = quota.value?.used_percentage ?? 0
  return Math.min(100, pct)
})

const quotaHint = 'Les versions précédentes des fichiers comptent dans le quota.'

const SOURCE_LABELS = {
  manual: 'Manuels',
  task: 'Tâches',
  finance: 'Finances'
}
const SOURCE_COLORS = {
  manual: '#3b82f6',
  task: '#8b5cf6',
  finance: '#f59e0b'
}

const totalBreakdownBytes = computed(() => {
  if (!quota.value) return 0
  return (quota.value.breakdown_by_source ?? []).reduce((sum, row) => sum + (row.bytes || 0), 0)
})

const segments = computed(() => {
  if (!quota.value) return []
  const total = totalBreakdownBytes.value
  return ['manual', 'task', 'finance']
    .map((key) => {
      const row = (quota.value.breakdown_by_source ?? []).find((r) => r.source === key)
      const bytes = row?.bytes ?? 0
      return {
        key,
        label: SOURCE_LABELS[key],
        color: SOURCE_COLORS[key],
        bytes,
        pct: total > 0 ? (bytes / total) * 100 : 0,
        widthPct: total > 0 ? (bytes / total) * 100 : 0
      }
    })
    .filter((s) => s.bytes > 0)
})

const progressBarClass = computed(() => {
  const pct = quota.value?.used_percentage ?? 0
  if (pct >= 100) return '!bg-red-500'
  if (pct >= 80) return '!bg-amber-500'
  return ''
})

const percentageClass = computed(() => {
  const pct = quota.value?.used_percentage ?? 0
  if (pct >= 100) return 'text-red-600 font-semibold'
  if (pct >= 80) return 'text-amber-600 font-semibold'
  return 'text-surface-500'
})

function formatPercentage(value) {
  if (value === null || value === undefined) return '—'
  return `${Math.round(value * 10) / 10} %`
}

onMounted(() => {
  if (bandSpaceId.value) {
    filesStore.fetchQuota(bandSpaceId.value)
  }
})
</script>
