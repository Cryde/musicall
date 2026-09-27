<template>
  <section
    class="flex flex-col gap-5 rounded-2xl border border-primary-200 dark:border-surface-700 bg-gradient-to-br from-primary-50 to-purple-50 dark:from-surface-900 dark:to-surface-900 p-5 lg:p-7"
    aria-labelledby="home-member-band-space-title"
  >
    <template v-if="space">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3.5 min-w-0">
          <span class="w-12 h-12 shrink-0 rounded-xl bg-gradient-to-br from-primary-400 to-fuchsia-400" aria-hidden="true" />
          <div class="flex flex-col min-w-0">
            <span class="text-xs font-bold uppercase tracking-widest text-primary">Mon Band Space</span>
            <h2 id="home-member-band-space-title" class="m-0 text-xl font-bold truncate text-surface-900 dark:text-surface-0">
              {{ space.name }}
            </h2>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Select
            v-if="bandSpaceStore.spaces.length > 1"
            :model-value="space.id"
            :options="bandSpaceStore.spaces"
            option-label="name"
            option-value="id"
            aria-label="Changer de groupe"
            placeholder="Changer de groupe"
            class="w-48"
            @update:model-value="switchTo"
          />
          <Button
            as="router-link"
            :to="{ name: 'app_band_dashboard', params: { id: space.id } }"
            label="Ouvrir le Band Space"
            icon="pi pi-arrow-right"
            icon-pos="right"
          />
        </div>
      </div>

      <div class="grid md:grid-cols-3 gap-3">
        <router-link :to="{ name: 'app_band_agenda', params: { id: space.id } }" :class="TILE">
          <span class="text-xs font-bold uppercase tracking-wide text-primary">Prochain événement</span>
          <span v-if="isLoadingDetails" :class="PLACEHOLDER" />
          <template v-else-if="nextEvents.length > 0">
            <div class="flex items-center gap-3">
              <div class="flex flex-col items-center w-11 shrink-0">
                <span class="text-xs font-bold uppercase text-primary">{{ dayOf(nextEvents[0]) }}</span>
                <span class="text-2xl font-bold text-surface-900 dark:text-surface-0">{{ dateOf(nextEvents[0]) }}</span>
              </div>
              <div class="flex flex-col min-w-0">
                <span class="font-semibold truncate text-surface-900 dark:text-surface-0">{{ nextEvents[0].title }}</span>
                <span class="text-sm text-surface-600 dark:text-surface-300">{{ timeOf(nextEvents[0]) }}</span>
              </div>
            </div>
            <span v-if="nextEvents[1]" class="text-sm text-surface-600 dark:text-surface-300 truncate">
              Puis : {{ nextEvents[1].title }}, {{ shortDateOf(nextEvents[1]) }}
            </span>
          </template>
          <span v-else class="text-sm text-surface-600 dark:text-surface-300">Rien de prévu ces {{ AGENDA_DAYS }} prochains jours.</span>
        </router-link>

        <router-link :to="{ name: 'app_band_tasks', params: { id: space.id } }" :class="TILE">
          <span class="text-xs font-bold uppercase tracking-wide text-teal-700 dark:text-teal-300">
            Tâches ouvertes<template v-if="!isLoadingDetails"> · {{ openTaskCount }}</template>
          </span>
          <span v-if="isLoadingDetails" :class="PLACEHOLDER" />
          <ul v-else-if="tasks.length > 0" class="m-0 p-0 list-none flex flex-col gap-1.5">
            <li v-for="task in tasks" :key="task.id" class="flex items-center gap-2 text-sm text-surface-900 dark:text-surface-0 min-w-0">
              <span class="w-3.5 h-3.5 shrink-0 rounded border-2 border-surface-400 dark:border-surface-500" aria-hidden="true" />
              <span class="truncate">{{ task.title }}</span>
            </li>
          </ul>
          <span v-else class="text-sm text-surface-600 dark:text-surface-300">Aucune tâche ouverte.</span>
        </router-link>

        <router-link :to="{ name: 'app_band_setlist', params: { id: space.id } }" :class="TILE">
          <span class="text-xs font-bold uppercase tracking-wide text-fuchsia-700 dark:text-fuchsia-300">Dernière setlist</span>
          <span v-if="isLoadingDetails" :class="PLACEHOLDER" />
          <template v-else-if="setlist">
            <span class="font-semibold truncate text-surface-900 dark:text-surface-0">{{ setlist.setlist.name }}</span>
            <span class="text-sm text-surface-600 dark:text-surface-300">
              {{ setlist.songCount }} morceau{{ setlist.songCount > 1 ? 'x' : '' }} · {{ formatDuration(setlist.setlist.total_duration_seconds) }}
            </span>
            <span class="text-sm text-surface-600 dark:text-surface-300">Modifiée {{ relativeDate(setlist.changedAt, { showHours: false }) }}</span>
          </template>
          <span v-else class="text-sm text-surface-600 dark:text-surface-300">Pas encore de setlist.</span>
        </router-link>
      </div>

      <p v-if="detailsError" class="m-0 text-sm text-red-700 dark:text-red-400" role="alert">{{ detailsError }}</p>
      <p class="m-0 mt-auto flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
        <i class="pi pi-lock text-xs" aria-hidden="true" />
        Visible uniquement par vous et les membres du groupe.
      </p>
    </template>

  </section>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'
import Button from 'primevue/button'
import Select from 'primevue/select'
import { onMounted, ref } from 'vue'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'
import bandSpaceSetlistsApi from '../../../api/bandSpace/band-space-setlists.js'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import relativeDate from '../../../helper/date/relative-date.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { toAgendaDate } from '../../../utils/agendaDate.js'
import { isAllDayItem } from '../../../utils/agendaItem.js'
import { upcomingAgendaWindow } from '../../../utils/agendaRange.js'
import { lastChangedSetlist, openTasks, pickBandSpace } from '../../../utils/memberHome.js'
import { formatDuration } from '../../../utils/setlistDuration.js'

/**
 * « Mon Band Space » on the logged in home (#1078): what is next for the band, at a glance. Only
 * shown to a member with a space; one without gets HomeMemberBandSpaceStrip instead.
 */
const AGENDA_DAYS = 30
const TASKS_SHOWN = 3
const TILE =
  'flex flex-col gap-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0/80 dark:bg-surface-950 p-4 hover:border-primary-300 dark:hover:border-surface-500 transition-colors min-w-0'
const PLACEHOLDER = 'h-10 rounded-md bg-surface-100 dark:bg-surface-800 animate-pulse'

const bandSpaceStore = useBandSpaceStore()
const { getLastSpaceId, setLastSpaceId } = useBandSpaceNavigation()

// The page loaded the spaces to choose this layout, so there is at least one to pick from.
const space = ref(pickBandSpace(bandSpaceStore.spaces, getLastSpaceId()))
const isLoadingDetails = ref(false)
const detailsError = ref(null)
const nextEvents = ref([])
const tasks = ref([])
const openTaskCount = ref(0)
const setlist = ref(null)
let detailsRequest = 0

onMounted(() => {
  if (space.value) loadDetails(space.value.id)
})

function switchTo(spaceId) {
  space.value = bandSpaceStore.getById(spaceId) ?? space.value
  setLastSpaceId(spaceId)
  loadDetails(spaceId)
}

// Only the latest space lands: switching twice quickly must not show the first one's agenda.
async function loadDetails(spaceId) {
  const request = ++detailsRequest
  isLoadingDetails.value = true
  detailsError.value = null
  try {
    const { from, to } = upcomingAgendaWindow(new Date(), AGENDA_DAYS)
    const [agenda, taskList, stats, setlists] = await Promise.all([
      bandSpaceAgendaApi.getAgenda(spaceId, { from, to }),
      bandSpaceTasksApi.getTasks(spaceId, { archived: false }),
      bandSpaceTasksApi.getStats(spaceId),
      bandSpaceSetlistsApi.getSetlists(spaceId)
    ])
    if (request !== detailsRequest) return
    nextEvents.value = [...agenda].sort((a, b) => a.datetime.localeCompare(b.datetime)).slice(0, 2)
    tasks.value = openTasks(taskList, TASKS_SHOWN)
    openTaskCount.value = stats.todo + stats.in_progress
    setlist.value = lastChangedSetlist(setlists)
  } catch {
    if (request !== detailsRequest) return
    detailsError.value = 'Impossible de charger le résumé du Band Space.'
  } finally {
    if (request === detailsRequest) isLoadingDetails.value = false
  }
}

function dateOfItem(item) {
  return toAgendaDate(item.datetime, isAllDayItem(item))
}

function dayOf(item) {
  const date = dateOfItem(item)
  return date ? format(date, 'EEE', { locale: fr }).replace('.', '') : ''
}

function dateOf(item) {
  const date = dateOfItem(item)
  return date ? format(date, 'dd') : ''
}

function shortDateOf(item) {
  const date = dateOfItem(item)
  return date ? format(date, 'EEE d', { locale: fr }) : ''
}

function timeOf(item) {
  return isAllDayItem(item) ? 'Toute la journée' : format(parseISO(item.datetime), 'HH:mm')
}
</script>
