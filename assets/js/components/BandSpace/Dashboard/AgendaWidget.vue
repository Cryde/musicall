<template>
  <DashboardWidget
    title="Agenda à venir"
    icon="pi pi-calendar"
    :is-loading="isLoading"
    :error="error"
    :is-empty="!isLoading && !error && items.length === 0"
    empty-message="Rien de prévu dans les 7 prochains jours."
  >
    <template #header-action>
      <RouterLink
        :to="{ name: 'app_band_agenda', params: { id: bandSpaceId } }"
        class="text-xs text-primary hover:underline"
      >
        Voir l'agenda
      </RouterLink>
    </template>

    <ul class="list-none p-0 m-0 flex flex-col gap-3">
      <li v-for="item in items" :key="item.id" class="flex gap-3 text-sm border-l-2 pl-3" :class="sourceBorderClass(item.source)">
        <div class="flex-1 min-w-0">
          <p class="font-medium text-surface-900 dark:text-surface-0 truncate">{{ item.title }}</p>
          <p class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
            {{ formatDayLabel(item.datetime) }} - {{ formatTime(item.datetime) }}
          </p>
        </div>
      </li>
    </ul>
  </DashboardWidget>
</template>

<script setup>
import { addDays, format, isToday, isTomorrow, parseISO, startOfDay } from 'date-fns'
import { fr } from 'date-fns/locale'
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'
import DashboardWidget from './DashboardWidget.vue'

const WINDOW_DAYS = 7
const MAX_ITEMS = 8

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const items = ref([])
const isLoading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const today = startOfDay(new Date())
    const from = format(today, "yyyy-MM-dd'T'00:00:00")
    const to = format(addDays(today, WINDOW_DAYS), "yyyy-MM-dd'T'23:59:59")
    const data = await bandSpaceAgendaApi.getAgenda(props.bandSpaceId, { from, to })
    items.value = [...data].sort((a, b) => a.datetime.localeCompare(b.datetime)).slice(0, MAX_ITEMS)
  } catch {
    error.value = "Impossible de charger l'agenda."
  } finally {
    isLoading.value = false
  }
})

function formatDayLabel(datetimeStr) {
  const date = parseISO(datetimeStr)
  if (isToday(date)) return "Aujourd'hui"
  if (isTomorrow(date)) return 'Demain'
  return format(date, 'EEEE d MMMM', { locale: fr })
}

function formatTime(datetimeStr) {
  return format(parseISO(datetimeStr), 'HH:mm')
}

const SOURCE_BORDER_CLASS = {
  manual: 'border-blue-400',
  task: 'border-amber-400',
  finance: 'border-emerald-400'
}

function sourceBorderClass(source) {
  return SOURCE_BORDER_CLASS[source] ?? 'border-surface-300'
}
</script>
