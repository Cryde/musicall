<template>
  <section
    class="flex flex-col gap-2 sm:gap-5 sm:rounded-2xl sm:border sm:border-surface-200 dark:sm:border-surface-700 sm:bg-surface-0 dark:sm:bg-surface-900 sm:p-5 lg:p-6"
    aria-labelledby="home-member-band-space-title"
  >
    <!-- On a phone a heading over four lines, like the other blocks of the home: the band (tap to
         switch), what is next, the open tasks, the last setlist. -->
    <template v-if="space && isPhone">
      <div class="flex items-baseline justify-between gap-3">
        <h2 id="home-member-band-space-title" class="m-0 text-[17px] font-semibold text-surface-900 dark:text-surface-0">
          {{ t('home.member.band_space.eyebrow') }}
        </h2>
        <router-link
          :to="{ name: 'app_band_dashboard', params: { id: space.id } }"
          :aria-label="t('home.member.band_space.open')"
          class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
        >
          {{ t('home.member.band_space.open_short') }}
          <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
        </router-link>
      </div>
      <ul class="m-0 p-0 list-none overflow-hidden rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 divide-y divide-surface-200 dark:divide-surface-700">
        <li>
          <button
            v-if="hasSeveralSpaces"
            type="button"
            aria-haspopup="menu"
            :aria-expanded="isSwitcherOpen"
            :class="[PHONE_ROW, 'w-full cursor-pointer border-0 bg-transparent text-left']"
            @click="switcherMenu.toggle($event)"
          >
            <span class="size-8 shrink-0 rounded-lg bg-primary-600 dark:bg-primary-400" aria-hidden="true" />
            <span :class="PHONE_TEXT">
              <span :class="PHONE_TITLE">{{ space.name }}</span>
              <span :class="PHONE_SUB">{{ t('home.member.band_space.switch') }}</span>
            </span>
            <i class="pi pi-chevron-down text-xs text-surface-500 dark:text-surface-400" aria-hidden="true" />
          </button>
          <div v-else :class="PHONE_ROW">
            <span class="size-8 shrink-0 rounded-lg bg-primary-600 dark:bg-primary-400" aria-hidden="true" />
            <span :class="[PHONE_TEXT, PHONE_TITLE]">{{ space.name }}</span>
          </div>
        </li>
        <li>
          <router-link :to="nextEventLink" :class="PHONE_ROW">
            <span v-if="!isLoadingDetails && nextEvents.length > 0" class="flex w-9 shrink-0 flex-col items-center">
              <span class="text-[11px] font-medium uppercase text-primary">{{ dayOf(nextEvents[0]) }}</span>
              <span class="text-lg leading-tight font-semibold tabular-nums text-surface-900 dark:text-surface-0">{{ dateOf(nextEvents[0]) }}</span>
            </span>
            <span v-else :class="PHONE_ICON"><i class="pi pi-calendar" aria-hidden="true" /></span>
            <span :class="PHONE_TEXT">
              <span v-if="isLoadingDetails" :class="PHONE_PLACEHOLDER" />
              <template v-else-if="nextEvents.length > 0">
                <span :class="PHONE_TITLE">{{ nextEvents[0].title }}</span>
                <span :class="PHONE_SUB">{{ nextEventDetail }}</span>
              </template>
              <template v-else>
                <span :class="PHONE_TITLE">{{ t('home.member.band_space.next_event') }}</span>
                <span :class="PHONE_SUB">{{ t('home.member.band_space.nothing_planned', { days: AGENDA_DAYS }) }}</span>
              </template>
            </span>
            <i :class="PHONE_CHEVRON" aria-hidden="true" />
          </router-link>
        </li>
        <li>
          <router-link :to="{ name: 'app_band_tasks', params: { id: space.id } }" :class="PHONE_ROW">
            <span :class="PHONE_ICON"><i class="pi pi-check-square" aria-hidden="true" /></span>
            <span :class="PHONE_TEXT">
              <span v-if="isLoadingDetails" :class="PHONE_PLACEHOLDER" />
              <template v-else>
                <span :class="PHONE_TITLE">{{ t('home.member.band_space.open_tasks') }}{{ ` ${separator()} ${openTaskCount}` }}</span>
                <span :class="PHONE_SUB">{{ tasks.length > 0 ? taskTitles : t('home.member.band_space.no_open_task') }}</span>
              </template>
            </span>
            <i :class="PHONE_CHEVRON" aria-hidden="true" />
          </router-link>
        </li>
        <li>
          <router-link :to="setlistLink" :class="PHONE_ROW">
            <span :class="PHONE_ICON"><i class="pi pi-list" aria-hidden="true" /></span>
            <span :class="PHONE_TEXT">
              <span v-if="isLoadingDetails" :class="PHONE_PLACEHOLDER" />
              <template v-else-if="setlist">
                <span :class="PHONE_TITLE">{{ setlist.setlist.name }}</span>
                <span :class="PHONE_SUB">{{ setlistDetail }}</span>
              </template>
              <template v-else>
                <span :class="PHONE_TITLE">{{ t('home.member.band_space.last_setlist') }}</span>
                <span :class="PHONE_SUB">{{ t('home.member.band_space.no_setlist') }}</span>
              </template>
            </span>
            <i :class="PHONE_CHEVRON" aria-hidden="true" />
          </router-link>
        </li>
      </ul>
      <p v-if="detailsError" class="m-0 text-sm text-red-700 dark:text-red-400" role="alert">{{ detailsError }}</p>
    </template>

    <template v-else-if="space">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3.5 min-w-0">
          <span class="w-12 h-12 shrink-0 rounded-xl bg-primary-600 dark:bg-primary-400" aria-hidden="true" />
          <div class="flex flex-col min-w-0">
            <span class="text-sm text-surface-600 dark:text-surface-300">{{ t('home.member.band_space.eyebrow') }}</span>
            <!-- The name is the switcher: that is where the eye already is, and it does not repeat it. -->
            <h2 id="home-member-band-space-title" class="m-0 min-w-0 text-xl font-semibold text-surface-900 dark:text-surface-0">
              <button
                v-if="hasSeveralSpaces"
                type="button"
                class="-mx-2 inline-flex max-w-[calc(100%+1rem)] items-center gap-2 rounded-lg px-2 py-0.5 cursor-pointer transition-colors duration-150 hover:bg-surface-100 dark:hover:bg-surface-800"
                aria-haspopup="menu"
                :aria-expanded="isSwitcherOpen"
                @click="switcherMenu.toggle($event)"
              >
                <span class="truncate">{{ space.name }}</span>
                <span class="sr-only">, {{ t('home.member.band_space.switch') }}</span>
                <i class="pi pi-chevron-down text-sm text-surface-500 dark:text-surface-400" aria-hidden="true" />
              </button>
              <span v-else class="block truncate">{{ space.name }}</span>
            </h2>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Button
            as="router-link"
            :to="{ name: 'app_band_dashboard', params: { id: space.id } }"
            :label="t('home.member.band_space.open')"
            icon="pi pi-arrow-right"
            icon-pos="right"
          />
        </div>
      </div>

      <div class="grid md:grid-cols-3 gap-3">
        <router-link :to="nextEventLink" :class="TILE">
          <span :class="TILE_LABEL">{{ t('home.member.band_space.next_event') }}</span>
          <span v-if="isLoadingDetails" :class="PLACEHOLDER" />
          <template v-else-if="nextEvents.length > 0">
            <div class="flex items-center gap-3">
              <div class="flex flex-col items-center w-11 shrink-0">
                <span class="text-xs font-medium uppercase text-primary">{{ dayOf(nextEvents[0]) }}</span>
                <span class="text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0">{{ dateOf(nextEvents[0]) }}</span>
              </div>
              <div class="flex flex-col min-w-0">
                <span class="font-semibold truncate text-surface-900 dark:text-surface-0">{{ nextEvents[0].title }}</span>
                <span class="text-sm tabular-nums text-surface-600 dark:text-surface-300">{{ timeOf(nextEvents[0]) }}</span>
              </div>
            </div>
            <span v-if="nextEvents[1]" class="text-sm text-surface-600 dark:text-surface-300 truncate">
              {{ t('home.member.band_space.then', { title: nextEvents[1].title, date: shortDateOf(nextEvents[1]) }) }}
            </span>
          </template>
          <span v-else class="text-sm text-surface-600 dark:text-surface-300">{{ t('home.member.band_space.nothing_planned', { days: AGENDA_DAYS }) }}</span>
        </router-link>

        <!-- Not one link like its neighbours: each task opens itself, and links cannot nest. -->
        <div :class="TILE_FRAME">
          <router-link
            :to="{ name: 'app_band_tasks', params: { id: space.id } }"
            class="self-start hover:underline focus-visible:underline" :class="TILE_LABEL"
          >
            {{ t('home.member.band_space.open_tasks') }}<template v-if="!isLoadingDetails">{{ ` ${separator()} ${openTaskCount}` }}</template>
          </router-link>
          <span v-if="isLoadingDetails" :class="PLACEHOLDER" />
          <ul v-else-if="tasks.length > 0" class="m-0 p-0 list-none flex flex-col gap-1.5">
            <li v-for="task in tasks" :key="task.id" class="flex items-center gap-2 text-sm text-surface-900 dark:text-surface-0 min-w-0">
              <span class="w-3.5 h-3.5 shrink-0 rounded border-2 border-surface-400 dark:border-surface-500" aria-hidden="true" />
              <router-link
                :to="{ name: 'app_band_tasks', params: { id: space.id }, query: { task: task.id } }"
                class="truncate hover:underline focus-visible:underline"
              >
                {{ task.title }}
              </router-link>
            </li>
          </ul>
          <span v-else class="text-sm text-surface-600 dark:text-surface-300">{{ t('home.member.band_space.no_open_task') }}</span>
        </div>

        <router-link :to="setlistLink" :class="TILE">
          <span :class="TILE_LABEL">{{ t('home.member.band_space.last_setlist') }}</span>
          <span v-if="isLoadingDetails" :class="PLACEHOLDER" />
          <template v-else-if="setlist">
            <span class="font-semibold truncate text-surface-900 dark:text-surface-0">{{ setlist.setlist.name }}</span>
            <span class="text-sm tabular-nums text-surface-600 dark:text-surface-300">
              {{ t('home.member.band_space.setlist_meta', { duration: formatDuration(setlist.setlist.total_duration_seconds) }, setlist.songCount) }}
            </span>
            <span class="text-sm text-surface-600 dark:text-surface-300">{{ t('home.member.band_space.modified', { date: relativeDate(setlist.changedAt, { showHours: false }) }) }}</span>
          </template>
          <span v-else class="text-sm text-surface-600 dark:text-surface-300">{{ t('home.member.band_space.no_setlist') }}</span>
        </router-link>
      </div>

      <p v-if="detailsError" class="m-0 text-sm text-red-700 dark:text-red-400" role="alert">{{ detailsError }}</p>
      <p class="m-0 flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
        <i class="pi pi-lock text-xs" aria-hidden="true" />
        {{ t('home.member.band_space.private') }}
      </p>
    </template>

    <!-- PrimeVue's popup does not tell its button whether it is open, so the state is kept here. -->
    <Menu ref="switcherMenu" :model="switcherItems" popup @show="isSwitcherOpen = true" @hide="isSwitcherOpen = false" />
  </section>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, onMounted, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'
import bandSpaceSetlistsApi from '../../../api/bandSpace/band-space-setlists.js'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import { useBandSpaceLiveRefresh } from '../../../composables/useBandSpaceLiveRefresh.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useIsPhone } from '../../../composables/useIsPhone.js'
import relativeDate from '../../../helper/date/relative-date.js'
import { dateFnsLocale, separator } from '../../../i18n/formatLocale.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { toAgendaDate } from '../../../utils/agendaDate.js'
import { agendaItemLink, isAllDayItem } from '../../../utils/agendaItem.js'
import { upcomingAgendaWindow } from '../../../utils/agendaRange.js'
import { lastChangedSetlist, openTasks, pickBandSpace } from '../../../utils/memberHome.js'
import { formatDuration } from '../../../utils/setlistDuration.js'

/**
 * « Mon Band Space » on the logged in home (#1078): what is next for the band, at a glance. Only
 * shown to a member with a space; one without gets HomeMemberBandSpaceStrip instead.
 */
const AGENDA_DAYS = 30
const TASKS_SHOWN = 3
const TILE_FRAME =
  'flex flex-col gap-2.5 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-950 p-4 min-w-0'
// A whole tile is a link; the tasks tile is not, so it keeps the frame without the hover.
const TILE = `${TILE_FRAME} hover:border-surface-300 dark:hover:border-surface-500 transition-colors duration-150`
const TILE_LABEL = 'text-sm font-medium text-surface-600 dark:text-surface-300'
const PLACEHOLDER = 'h-10 rounded-md bg-surface-100 dark:bg-surface-800 animate-pulse'
const PHONE_ROW =
  'flex items-center gap-3 px-4 py-2.5 hover:bg-surface-50 dark:hover:bg-surface-800/60'
const PHONE_ICON =
  'inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-surface-100 dark:bg-surface-800 text-sm text-surface-600 dark:text-surface-300'
const PHONE_TEXT = 'flex min-w-0 flex-1 flex-col'
const PHONE_TITLE = 'truncate text-[15px] font-semibold text-surface-900 dark:text-surface-0'
const PHONE_SUB = 'truncate text-[13px] text-surface-600 dark:text-surface-300'
const PHONE_CHEVRON = 'pi pi-chevron-right text-xs text-surface-500 dark:text-surface-400'
const PHONE_PLACEHOLDER = 'h-9 rounded-md bg-surface-100 dark:bg-surface-800 animate-pulse'

const { t } = useI18n()
const isPhone = useIsPhone()
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

// The tiles open what they show (#1159), and the module when they show nothing.
const nextEventLink = computed(
  () =>
    (nextEvents.value[0] && agendaItemLink(nextEvents.value[0], space.value.id)) || {
      name: 'app_band_agenda',
      params: { id: space.value.id }
    }
)
const hasSeveralSpaces = computed(() => bandSpaceStore.spaces.length > 1)

// A phone line says in one sentence what a tile shows over three.
const nextEventDetail = computed(() =>
  [
    timeOf(nextEvents.value[0]),
    nextEvents.value[1] &&
      t('home.member.band_space.then', {
        title: nextEvents.value[1].title,
        date: shortDateOf(nextEvents.value[1])
      })
  ]
    .filter(Boolean)
    .join(` ${separator()} `)
)
const taskTitles = computed(() => tasks.value.map((task) => task.title).join(', '))
const setlistDetail = computed(() =>
  [
    t('home.member.band_space.last_setlist'),
    t(
      'home.member.band_space.setlist_meta',
      { duration: formatDuration(setlist.value.setlist.total_duration_seconds) },
      setlist.value.songCount
    )
  ].join(` ${separator()} `)
)

const setlistLink = computed(() => ({
  name: 'app_band_setlist',
  params: { id: space.value.id },
  query: setlist.value ? { setlist: setlist.value.setlist.id } : {}
}))

onMounted(() => {
  if (space.value) loadDetails(space.value.id)
})

const switcherMenu = useTemplateRef('switcherMenu')
const isSwitcherOpen = ref(false)
// The blank fixed-width icon keeps every name on the same column as the ticked one.
const switcherItems = computed(() =>
  bandSpaceStore.spaces.map((bandSpace) => ({
    label: bandSpace.name,
    icon: bandSpace.id === space.value?.id ? 'pi pi-fw pi-check' : 'pi pi-fw',
    command: () => switchTo(bandSpace.id)
  }))
)

function switchTo(spaceId) {
  space.value = bandSpaceStore.getById(spaceId) ?? space.value
  setLastSpaceId(spaceId)
  loadDetails(spaceId)
}

// The agenda feed carries task due dates and finance entries too (#1157).
useBandSpaceLiveRefresh({
  bandSpaceId: () => space.value?.id ?? null,
  modules: ['agenda', 'task', 'finance', 'setlist'],
  refresh: () => {
    if (space.value) loadDetails(space.value.id, { quiet: true })
  }
})

/**
 * Only the latest space lands: switching twice quickly must not show the first one's agenda.
 * `quiet` keeps what is on screen, with no placeholders and no error, for a live refetch.
 */
async function loadDetails(spaceId, { quiet = false } = {}) {
  const request = ++detailsRequest
  if (!quiet) {
    isLoadingDetails.value = true
    detailsError.value = null
  }
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
    if (request !== detailsRequest || quiet) return
    detailsError.value = t('home.member.band_space.load_error')
  } finally {
    if (request === detailsRequest) isLoadingDetails.value = false
  }
}

function dateOfItem(item) {
  return toAgendaDate(item.datetime, isAllDayItem(item))
}

function dayOf(item) {
  const date = dateOfItem(item)
  return date ? format(date, 'EEE', { locale: dateFnsLocale() }).replace('.', '') : ''
}

function dateOf(item) {
  const date = dateOfItem(item)
  return date ? format(date, 'dd') : ''
}

function shortDateOf(item) {
  const date = dateOfItem(item)
  return date ? format(date, 'EEE d', { locale: dateFnsLocale() }) : ''
}

function timeOf(item) {
  return isAllDayItem(item)
    ? t('home.member.band_space.all_day')
    : format(parseISO(item.datetime), 'HH:mm')
}
</script>
