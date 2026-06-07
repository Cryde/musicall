<template>
  <DashboardWidget
    title="Activité récente"
    icon="pi pi-history"
    :is-loading="isLoading"
    :error="error"
    :is-empty="!isLoading && !error && items.length === 0"
    empty-message="Aucune activité pour le moment."
  >
    <template #header-action>
      <RouterLink
        :to="{ name: 'app_band_parameters', params: { id: bandSpaceId }, query: { section: 'activity' } }"
        class="text-xs text-primary hover:underline"
      >
        Tout voir
      </RouterLink>
    </template>

    <ul class="list-none p-0 m-0 flex flex-col gap-3">
      <li v-for="activity in items" :key="activity.id" class="flex gap-3 text-sm">
        <i :class="['pi mt-0.5 text-surface-400', moduleIcon(activity.module)]" aria-hidden="true" />
        <div class="flex-1 min-w-0">
          <p class="text-surface-700 dark:text-surface-200 leading-snug">
            <span class="font-medium">{{ activity.actor?.username || 'Système' }}</span>
            {{ activitySentence(activity) }}
          </p>
          <p class="text-xs text-surface-400 mt-0.5">{{ formatRelative(activity.creation_datetime) }}</p>
        </div>
      </li>
    </ul>
  </DashboardWidget>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import bandSpaceActivityApi from '../../../api/bandSpace/band-space-activity.js'
import { activitySentence as buildSentence } from '../Settings/activitySentences.js'
import DashboardWidget from './DashboardWidget.vue'

const ITEMS_LIMIT = 10

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const items = ref([])
const isLoading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const data = await bandSpaceActivityApi.list(props.bandSpaceId, { page: 1 })
    items.value = (data.member ?? []).slice(0, ITEMS_LIMIT)
  } catch {
    error.value = "Impossible de charger l'activité récente."
  } finally {
    isLoading.value = false
  }
})

function activitySentence(activity) {
  return buildSentence(activity)
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: fr })
}

const MODULE_ICONS = {
  task: 'pi-check-square',
  finance: 'pi-wallet',
  agenda: 'pi-calendar',
  notes: 'pi-file-edit',
  file: 'pi-folder-open',
  setlist: 'pi-list',
  settings: 'pi-cog'
}

function moduleIcon(module) {
  return MODULE_ICONS[module] ?? 'pi-circle'
}
</script>
