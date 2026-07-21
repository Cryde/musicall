<template>
  <div class="p-3 sm:p-4">
    <div class="flex items-center justify-between gap-2 mb-4">
      <h1 class="text-xl sm:text-2xl font-bold">Agenda</h1>
      <Button
        icon="pi pi-plus"
        label="Nouvel événement"
        size="small"
        :pt="{ label: { class: 'hidden sm:inline' } }"
        aria-label="Nouvel événement"
        @click="openCreateDialog"
      />
    </div>

    <div class="flex flex-col gap-3 mb-4 sm:flex-row sm:flex-wrap sm:items-center">
      <DateRangePicker
        :from="dateFrom"
        :to="dateTo"
        :presets="agendaPresets"
        @apply="handleDateRangeApply"
      />
      <div class="flex items-center gap-1 -mx-3 px-3 overflow-x-auto sm:overflow-visible sm:mx-0 sm:px-0">
        <button
          v-for="src in sourceOptions"
          :key="src.key"
          type="button"
          class="text-xs font-medium px-2.5 py-1 rounded-full transition-all text-center tabular-nums whitespace-nowrap sm:min-w-20"
          :class="selectedSources.has(src.key) ? src.activeClass : src.inactiveClass"
          @click="toggleSource(src.key)"
        >
          {{ src.label }} · {{ countsBySource[src.key] }}
        </button>
      </div>
      <div class="flex items-center gap-1 sm:ml-auto">
        <Button
          v-for="mode in viewModeOptions"
          :key="mode.key"
          :icon="mode.icon"
          :label="mode.label"
          size="small"
          :severity="viewMode === mode.key ? 'primary' : 'secondary'"
          :outlined="viewMode !== mode.key"
          :text="viewMode !== mode.key"
          :pt="{ label: { class: 'hidden sm:inline' } }"
          :aria-label="mode.label"
          :title="mode.label"
          @click="selectViewMode(mode.key)"
        />
      </div>
    </div>

    <div v-if="agendaStore.isLoading" class="flex items-center justify-center py-12">
      <ProgressSpinner />
    </div>

    <div v-else-if="agendaStore.loadError" class="text-center text-red-500 py-12">
      {{ agendaStore.loadError }}
    </div>

    <template v-else-if="viewMode === 'list'">
      <div
        v-if="agendaStore.items.length === 0"
        class="text-center text-surface-400 italic py-12"
      >
        Aucun événement à venir dans cette période
      </div>

      <div
        v-else-if="filteredItems.length === 0"
        class="text-center text-surface-400 italic py-12"
      >
        Aucun événement avec ces filtres
      </div>

      <div v-else>
        <div v-for="group in groupedItems" :key="group.date" class="mb-6">
          <div class="flex items-center gap-2 mb-2 px-2">
            <span class="text-sm font-semibold text-surface-600 dark:text-surface-300">
              {{ formatDateLabel(group.date) }}
            </span>
            <span class="text-xs text-surface-400">
              {{ group.items.length }} événement{{ group.items.length > 1 ? 's' : '' }}
            </span>
          </div>
          <div
            class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden"
          >
            <div
              v-for="item in group.items"
              :key="item.id"
              class="flex items-start gap-3 p-3 border-l-4 border-b border-surface-200 dark:border-surface-700 last:border-b-0 transition-colors cursor-pointer hover:bg-surface-100 dark:hover:bg-surface-700"
              :class="sourceBorderClass(item.source)"
              @click="handleItemClick(item)"
            >
              <span class="text-sm font-medium tabular-nums text-surface-600 dark:text-surface-300 flex-shrink-0 pt-0.5 whitespace-nowrap">
                <template v-if="isAllDayItem(item)">
                  <span class="italic text-xs">Toute la journée</span>
                </template>
                <template v-else>
                  {{ formatTime(item.datetime) }}<template v-if="item.end_datetime"> → {{ formatTime(item.end_datetime) }}</template>
                </template>
              </span>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium truncate">{{ item.title }}</span>
                  <span
                    class="text-xs font-medium px-2 py-0.5 rounded-full flex-shrink-0"
                    :class="sourceBadgeClass(item.source)"
                  >
                    {{ sourceLabel(item.source) }}
                  </span>
                </div>

                <div
                  v-if="item.description"
                  class="text-sm text-surface-500 dark:text-surface-400 mt-0.5 line-clamp-2"
                >
                  {{ item.description }}
                </div>

                <div v-if="metadataLine(item)" class="text-xs text-surface-400 mt-1">
                  {{ metadataLine(item) }}
                </div>

                <div
                  v-if="item.source === 'task' && item.metadata?.assignees?.length"
                  class="flex items-center gap-1 mt-1.5"
                >
                  <Avatar
                    v-for="a in item.metadata.assignees.slice(0, 3)"
                    :key="a.id"
                    :username="a.username"
                    :picture-url="a.profile_picture_url"
                    size="sm"
                  />
                  <span
                    v-if="item.metadata.assignees.length > 3"
                    class="text-xs font-medium text-surface-500 dark:text-surface-400"
                  >
                    +{{ item.metadata.assignees.length - 3 }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="overflow-x-auto">
      <div
        class="agenda-fc-theme bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-2 sm:p-4"
        :class="{ 'min-w-[680px]': viewMode === 'timeGridWeek' }"
      >
        <FullCalendar :key="viewMode" :options="calendarOptions">
          <template #eventContent="arg">
            <AgendaEventChip :item="arg.event.extendedProps.item" :time-text="arg.timeText" :view-type="arg.view.type" />
          </template>
          <template #dayCellTopContent="arg">
            <template v-if="arg.view.type === 'multiMonthYear'">
              <span>{{ arg.dayNumberText }}</span>
              <span
                v-if="dayEventSources(arg.date).length"
                class="agenda-year-dots"
                role="img"
                :aria-label="yearDotsLabel(dayEventSources(arg.date))"
              >
                <span
                  v-for="(source, index) in dayEventSources(arg.date).slice(0, YEAR_DOT_LIMIT)"
                  :key="`${source}-${index}`"
                  class="agenda-year-dot"
                  :style="{ backgroundColor: SOURCE_COLORS[source] }"
                />
              </span>
            </template>
            <template v-else>{{ arg.dayNumberText }}</template>
          </template>
        </FullCalendar>
      </div>
    </div>

    <AgendaEntryDrawer
      v-model:visible="dialogVisible"
      :bandSpaceId="route.params.id"
      :agendaItem="dialogItem"
      :initialDatetime="dialogInitialDatetime"
    />
  </div>
</template>

<script setup>
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/vue3/daygrid'
import interactionPlugin from '@fullcalendar/vue3/interaction'
import frLocale from '@fullcalendar/vue3/locales/fr'
import multiMonthPlugin from '@fullcalendar/vue3/multimonth'
import classicTheme from '@fullcalendar/vue3/themes/classic'
import timeGridPlugin from '@fullcalendar/vue3/timegrid'
// v7 no longer bundles CSS; the classic theme's stylesheets must be imported explicitly.
import '@fullcalendar/vue3/skeleton.css'
import '@fullcalendar/vue3/themes/classic/theme.css'
import '@fullcalendar/vue3/themes/classic/palette.css'
import {
  addDays,
  endOfMonth,
  endOfWeek,
  format,
  parseISO,
  startOfDay,
  startOfMonth,
  startOfWeek
} from 'date-fns'
import { fr } from 'date-fns/locale'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DateRangePicker from '../../components/Admin/DateRangePicker.vue'
import AgendaEntryDrawer from '../../components/BandSpace/Agenda/AgendaEntryDrawer.vue'
import AgendaEventChip from '../../components/BandSpace/Agenda/AgendaEventChip.vue'
import Avatar from '../../components/User/Avatar.vue'
import { useBandAgendaStore } from '../../store/bandSpace/bandSpaceAgenda.js'
import { formatDateCompactWithYear } from '../../utils/date.js'

const route = useRoute()
const router = useRouter()
const agendaStore = useBandAgendaStore()
// Wipe any previous space's items synchronously before the first render so
// switching from /band/A/agenda to /band/B/agenda doesn't flash A's entries
// while B's fetch is in flight. The :key on <router-view> remounts this view
// on space switch but the Pinia store itself is an app-singleton and keeps
// A's state until cleared.
agendaStore.clear()

const dialogVisible = ref(false)
const dialogItem = ref(null)
const dialogInitialDatetime = ref(null)

const today = startOfDay(new Date())
const dateFrom = ref(today)
const dateTo = ref(addDays(today, 30))

const viewMode = ref('list')
// When drilling from the year overview into a day, that day's view must open on the
// clicked date rather than the current range start; null means "use dateFrom".
const focusDate = ref(null)
const viewModeOptions = [
  { key: 'list', label: 'Planning', icon: 'pi pi-list' },
  { key: 'timeGridDay', label: 'Jour', icon: 'pi pi-clock' },
  { key: 'timeGridWeek', label: 'Semaine', icon: 'pi pi-calendar-clock' },
  { key: 'dayGridMonth', label: 'Mois', icon: 'pi pi-calendar' },
  { key: 'multiMonthYear', label: 'Année', icon: 'pi pi-th-large' }
]

const agendaPresets = [
  {
    key: 'next_7d',
    label: '7 prochains jours',
    from: () => startOfDay(new Date()),
    to: () => addDays(startOfDay(new Date()), 6)
  },
  {
    key: 'next_14d',
    label: '14 prochains jours',
    from: () => startOfDay(new Date()),
    to: () => addDays(startOfDay(new Date()), 13)
  },
  {
    key: 'next_30d',
    label: '30 prochains jours',
    from: () => startOfDay(new Date()),
    to: () => addDays(startOfDay(new Date()), 29)
  },
  {
    key: 'this_week',
    label: 'Cette semaine',
    from: () => startOfWeek(new Date(), { locale: fr }),
    to: () => endOfWeek(new Date(), { locale: fr })
  },
  {
    key: 'this_month',
    label: 'Ce mois',
    from: () => startOfMonth(new Date()),
    to: () => endOfMonth(new Date())
  },
  {
    key: 'next_month',
    label: 'Le mois prochain',
    from: () => startOfMonth(addDays(endOfMonth(new Date()), 1)),
    to: () => endOfMonth(addDays(endOfMonth(new Date()), 1))
  }
]

const sourceOptions = [
  {
    key: 'manual',
    label: 'Manuel',
    activeClass: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    inactiveClass:
      'bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500 line-through'
  },
  {
    key: 'task',
    label: 'Tâches',
    activeClass: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    inactiveClass:
      'bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500 line-through'
  },
  {
    key: 'finance',
    label: 'Finances',
    activeClass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    inactiveClass:
      'bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500 line-through'
  }
]

const SOURCE_COLORS = {
  manual: '#3b82f6',
  task: '#f59e0b',
  finance: '#10b981'
}

// Max dots drawn per day in the year overview; any extra events are reflected in the
// aria-label count only.
const YEAR_DOT_LIMIT = 10

const selectedSources = reactive(new Set(['manual', 'task', 'finance']))

const countsBySource = computed(() => {
  const counts = { manual: 0, task: 0, finance: 0 }
  for (const item of agendaStore.items) {
    if (counts[item.source] !== undefined) {
      counts[item.source]++
    }
  }
  return counts
})

const filteredItems = computed(() =>
  agendaStore.items.filter((item) => selectedSources.has(item.source))
)

const groupedItems = computed(() => {
  const groups = new Map()
  for (const item of filteredItems.value) {
    for (const date of itemDateKeys(item)) {
      if (!groups.has(date)) {
        groups.set(date, [])
      }
      groups.get(date).push(item)
    }
  }
  // Sort days ascending, then within each day put all-day items first, then by start time.
  const sortedKeys = Array.from(groups.keys()).sort()
  return sortedKeys.map((date) => ({
    date,
    items: groups.get(date).sort((a, b) => {
      const aAllDay = isAllDayItem(a)
      const bAllDay = isAllDayItem(b)
      if (aAllDay !== bAllDay) return aAllDay ? -1 : 1
      return a.datetime.localeCompare(b.datetime)
    })
  }))
})

function itemDateKeys(item) {
  const startKey = format(parseISO(item.datetime), 'yyyy-MM-dd')
  // Only all-day events expand across days — a timed multi-day event would mislead with
  // its start-day time range showing on every intermediate day.
  if (!item.is_all_day || !item.end_datetime) return [startKey]
  const endKey = format(parseISO(item.end_datetime), 'yyyy-MM-dd')
  if (endKey === startKey) return [startKey]
  const keys = []
  let cursor = parseISO(item.datetime)
  const end = parseISO(item.end_datetime)
  while (cursor <= end) {
    keys.push(format(cursor, 'yyyy-MM-dd'))
    cursor = addDays(cursor, 1)
  }
  return keys
}

// One entry per event on each day (the same source repeats when a day has several events of
// that kind), for the year overview's dots. Built from filteredItems so the source chips
// filter the dots too.
const dayEventSourcesByDate = computed(() => {
  const map = new Map()
  for (const item of filteredItems.value) {
    for (const key of itemDateKeys(item)) {
      if (!map.has(key)) map.set(key, [])
      map.get(key).push(item.source)
    }
  }
  return map
})

function dayEventSources(date) {
  // FullCalendar's default timeZone is 'local', so the cell date passed here is local midnight
  // of the day shown; format it with the same local getters as itemDateKeys so the dot lookup
  // lines up with how events are keyed. (Do NOT switch to UTC getters: east of UTC that reads
  // the previous calendar day and shifts every dot one day forward.)
  return dayEventSourcesByDate.value.get(format(date, 'yyyy-MM-dd')) ?? []
}

function yearDotsLabel(sources) {
  const distinct = Object.keys(SOURCE_COLORS).filter((source) => sources.includes(source))
  return `${sources.length} événement${sources.length > 1 ? 's' : ''} : ${distinct.map(sourceLabel).join(', ')}`
}

const calendarEvents = computed(() =>
  filteredItems.value.map((item) => ({
    id: item.id,
    title: item.title,
    start: item.datetime,
    end: calendarEnd(item),
    allDay: isAllDayItem(item),
    color: SOURCE_COLORS[item.source] ?? '#94a3b8',
    contrastColor: '#fff',
    className: 'agenda-event-clickable',
    extendedProps: { item }
  }))
)

const calendarOptions = computed(() => ({
  plugins: [classicTheme, dayGridPlugin, timeGridPlugin, multiMonthPlugin, interactionPlugin],
  initialView: viewMode.value === 'list' ? 'dayGridMonth' : viewMode.value,
  initialDate: focusDate.value ?? dateFrom.value,
  locale: frLocale,
  firstDay: 1,
  // The year overview draws its own source dots (dayCellTopContent), so it renders no
  // native events - keeps the tiny month cells clean.
  events: viewMode.value === 'multiMonthYear' ? [] : calendarEvents.value,
  eventClick: handleEventClick,
  dateClick: handleDateClick,
  datesSet: handleDatesSet,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: ''
  },
  todayText: "Aujourd'hui",
  height: 'auto',
  allDaySlot: true,
  allDayText: 'Toute la journée',
  slotMinTime: '06:00:00',
  slotMaxTime: '24:00:00',
  nowIndicator: true,
  dayMaxEvents: 3
}))

onMounted(() => {
  fetchWithCurrentRange()
})

function fetchRange(from, to) {
  const fromIso = format(from, "yyyy-MM-dd'T'00:00:00")
  const toIso = format(to, "yyyy-MM-dd'T'23:59:59")
  agendaStore.fetchAgenda(route.params.id, { from: fromIso, to: toIso })
}

function fetchWithCurrentRange() {
  fetchRange(dateFrom.value, dateTo.value)
}

function handleDateRangeApply({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  fetchWithCurrentRange()
}

function selectViewMode(key) {
  // A manual view switch clears any year-overview drill-down so the view opens on the
  // current range rather than a previously drilled-into day.
  focusDate.value = null
  viewMode.value = key
}

function toggleSource(key) {
  if (selectedSources.has(key)) {
    selectedSources.delete(key)
  } else {
    selectedSources.add(key)
  }
}

function openCreateDialog() {
  dialogItem.value = null
  dialogInitialDatetime.value = null
  dialogVisible.value = true
}

function handleItemClick(item) {
  if (!item) return
  if (item.source === 'manual') {
    dialogItem.value = item
    dialogInitialDatetime.value = null
    dialogVisible.value = true
    return
  }
  if (item.source === 'task') {
    router.push({
      name: 'app_band_tasks',
      params: { id: route.params.id },
      query: { task: item.source_id }
    })
    return
  }
  if (item.source === 'finance') {
    router.push({
      name: 'app_band_finance',
      params: { id: route.params.id },
      query: { entry: item.source_id }
    })
  }
}

function handleEventClick(info) {
  handleItemClick(info.event.extendedProps.item)
}

function handleDateClick(info) {
  if (viewMode.value === 'multiMonthYear') {
    // From the year overview, a day click drills into that day rather than creating an entry.
    focusDate.value = new Date(info.date.getTime())
    viewMode.value = 'timeGridDay'
    return
  }
  const clickedDate = new Date(info.date.getTime())
  if (info.allDay) {
    clickedDate.setHours(9, 0, 0, 0)
  }
  dialogItem.value = null
  dialogInitialDatetime.value = clickedDate
  dialogVisible.value = true
}

function handleDatesSet(arg) {
  const visibleStart = startOfDay(arg.start)
  const visibleEndInclusive = startOfDay(new Date(arg.end.getTime() - 1))

  // The year overview spans a whole year; load it on its own without dragging the shared
  // date-range picker (and every other view's initialDate anchor) out to a Jan-Dec window.
  if (viewMode.value === 'multiMonthYear') {
    fetchRange(visibleStart, visibleEndInclusive)
    return
  }

  let newFrom = dateFrom.value
  let newTo = dateTo.value
  let needsRefetch = false

  if (visibleStart < dateFrom.value) {
    newFrom = visibleStart
    needsRefetch = true
  }
  if (visibleEndInclusive > dateTo.value) {
    newTo = visibleEndInclusive
    needsRefetch = true
  }

  if (needsRefetch) {
    dateFrom.value = newFrom
    dateTo.value = newTo
    fetchWithCurrentRange()
  }
}

function formatDateLabel(dateString) {
  return formatDateCompactWithYear(dateString)
}

function formatTime(datetimeString) {
  return format(parseISO(datetimeString), 'HH:mm')
}

function calendarEnd(item) {
  if (!item.end_datetime) return undefined
  if (!item.is_all_day) return item.end_datetime
  // FullCalendar all-day events use exclusive end: bump last-day-inclusive to the day after.
  return format(addDays(parseISO(item.end_datetime), 1), 'yyyy-MM-dd')
}

function isAllDayItem(item) {
  return item.is_all_day || item.source === 'finance' || item.source === 'task'
}

function sourceLabel(source) {
  switch (source) {
    case 'manual':
      return 'Manuel'
    case 'task':
      return 'Tâche'
    case 'finance':
      return 'Finance'
    default:
      return source
  }
}

function sourceBorderClass(source) {
  switch (source) {
    case 'manual':
      return 'border-l-blue-500'
    case 'task':
      return 'border-l-amber-500'
    case 'finance':
      return 'border-l-emerald-500'
    default:
      return 'border-l-surface-300'
  }
}

function sourceBadgeClass(source) {
  switch (source) {
    case 'manual':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'task':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
    case 'finance':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
    default:
      return 'bg-surface-100 text-surface-600'
  }
}

function metadataLine(item) {
  const parts = []
  if (item.source === 'manual' && item.metadata?.location) {
    parts.push(item.metadata.location)
  }
  if (item.source === 'task') {
    if (item.metadata?.category_name) parts.push(item.metadata.category_name)
    if (item.metadata?.priority && item.metadata.priority !== 'normal') {
      parts.push(taskPriorityLabel(item.metadata.priority))
    }
  }
  if (item.source === 'finance') {
    if (item.metadata?.category_name) parts.push(item.metadata.category_name)
    if (item.metadata?.type) {
      parts.push(item.metadata.type === 'expense' ? 'Dépense' : 'Revenu')
    }
  }
  return parts.join(' · ')
}

function taskPriorityLabel(priority) {
  switch (priority) {
    case 'high':
      return 'Priorité haute'
    case 'urgent':
      return 'Urgent'
    default:
      return priority
  }
}
</script>

<style>
/*
 * FullCalendar v7 styles itself through the "classic" theme, which reads a set of
 * --fc-classic-* custom properties (their defaults live at :root / [data-color-scheme=dark]
 * in the theme's palette.css). This app drives dark mode with a .dark-mode class rather than
 * data-color-scheme, so we remap those variables onto PrimeVue tokens here: the semantic
 * tokens (--p-text-*, --p-primary-color) flip on their own, and the static surface-scale
 * tokens get an explicit .dark-mode override. Setting --fc-classic-foreground replaces the
 * per-cell text-colour rules v6 needed (the old .fc-* class names no longer exist in v7).
 */
.agenda-fc-theme {
  --fc-classic-border: var(--p-surface-200);
  --fc-classic-background: transparent;
  --fc-classic-faint: var(--p-surface-50);
  --fc-classic-today: color-mix(in oklch, var(--p-primary-color) 8%, transparent);
  --fc-classic-foreground: var(--p-text-color);
  --fc-classic-muted-foreground: var(--p-text-muted-color);
}

.dark-mode .agenda-fc-theme {
  --fc-classic-border: var(--p-surface-700);
  --fc-classic-faint: var(--p-surface-900);
}

.agenda-event-clickable {
  cursor: pointer;
}

.agenda-fc-theme .agenda-year-dots {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 2px;
  margin-top: 1px;
}

.agenda-fc-theme .agenda-year-dot {
  width: 5px;
  height: 5px;
  border-radius: 9999px;
}
</style>
