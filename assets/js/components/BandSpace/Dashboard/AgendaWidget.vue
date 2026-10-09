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
          <p class="font-medium text-surface-900 dark:text-surface-0 truncate">
            <RouterLink
              v-if="agendaItemLink(item, bandSpaceId)"
              :to="agendaItemLink(item, bandSpaceId)"
              class="hover:underline focus-visible:underline"
            >
              {{ item.title }}
            </RouterLink>
            <template v-else>{{ item.title }}</template>
          </p>
          <p class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">
            {{ formatDayLabel(item) }}<template v-if="!isAllDayItem(item)"> - {{ formatTime(item.datetime) }}</template>
          </p>
          <div
            v-if="item.source === 'manual' && item.metadata?.ask_availability"
            class="flex flex-wrap items-center justify-between gap-2 mt-1.5"
          >
            <span
              v-if="availabilitySummary(item.metadata.availability)"
              class="text-xs text-surface-600 dark:text-surface-300"
            >
              {{ availabilitySummary(item.metadata.availability) }}
            </span>
            <AgendaAvailabilityButtons
              v-if="canAnswerFromAgenda(item)"
              :band-space-id="bandSpaceId"
              :item="item"
              @answered="handleAnswered"
            />
          </div>
        </div>
      </li>
    </ul>
  </DashboardWidget>
</template>

<script setup>
import { format, isToday, isTomorrow, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'
import { useBandSpaceLiveRefresh } from '../../../composables/useBandSpaceLiveRefresh.js'
import { agendaSourceFor } from '../../../constants/agendaSources.js'
import {
  availabilitySummary,
  canAnswerFromAgenda,
  withAvailabilityAnswer
} from '../../../utils/agendaAvailability.js'
import { toAgendaDate } from '../../../utils/agendaDate.js'
import { agendaItemLink, isAllDayItem } from '../../../utils/agendaItem.js'
import { upcomingAgendaWindow } from '../../../utils/agendaRange.js'
import AgendaAvailabilityButtons from '../Agenda/AgendaAvailabilityButtons.vue'
import DashboardWidget from './DashboardWidget.vue'

const WINDOW_DAYS = 7
const MAX_ITEMS = 8

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const items = ref([])
const isLoading = ref(true)
const error = ref(null)

/** `quiet`: a live refetch (#1157), which keeps what is on screen when it fails. */
async function load({ quiet = false } = {}) {
  try {
    const { from, to } = upcomingAgendaWindow(new Date(), WINDOW_DAYS)
    const data = await bandSpaceAgendaApi.getAgenda(props.bandSpaceId, { from, to })
    items.value = [...data].sort((a, b) => a.datetime.localeCompare(b.datetime)).slice(0, MAX_ITEMS)
    error.value = null
  } catch {
    if (quiet) return
    error.value = "Impossible de charger l'agenda."
  } finally {
    isLoading.value = false
  }
}

useBandSpaceLiveRefresh({
  bandSpaceId: () => props.bandSpaceId,
  modules: ['agenda', 'task', 'finance'],
  refresh: () => load({ quiet: true })
})

onMounted(() => load())

function handleAnswered({ entryId, occurrenceDate, availability }) {
  items.value = withAvailabilityAnswer(items.value, entryId, occurrenceDate, availability)
}

function formatDayLabel(item) {
  const date = toAgendaDate(item.datetime, isAllDayItem(item))
  if (date === null) return ''
  if (isToday(date)) return "Aujourd'hui"
  if (isTomorrow(date)) return 'Demain'
  return format(date, 'EEEE d MMMM', { locale: fr })
}

function formatTime(datetimeStr) {
  return format(parseISO(datetimeStr), 'HH:mm')
}

function sourceBorderClass(source) {
  return agendaSourceFor(source).widgetBorderClass
}
</script>
