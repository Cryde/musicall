<template>
  <div class="p-4">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Agenda</h1>
      <Button
        icon="pi pi-plus"
        label="Nouvel événement"
        size="small"
        @click="openCreateDialog"
      />
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
      <DateRangePicker
        :from="dateFrom"
        :to="dateTo"
        :presets="agendaPresets"
        @apply="handleDateRangeApply"
      />
      <div class="flex items-center gap-1">
        <button
          v-for="src in sourceOptions"
          :key="src.key"
          type="button"
          class="text-xs font-medium px-2.5 py-1 rounded-full transition-all min-w-20 text-center tabular-nums"
          :class="selectedSources.has(src.key) ? src.activeClass : src.inactiveClass"
          @click="toggleSource(src.key)"
        >
          {{ src.label }} · {{ countsBySource[src.key] }}
        </button>
      </div>
      <div class="flex items-center gap-1 ml-auto">
        <Button
          v-for="mode in viewModeOptions"
          :key="mode.key"
          :label="mode.label"
          size="small"
          :severity="viewMode === mode.key ? 'primary' : 'secondary'"
          :outlined="viewMode !== mode.key"
          :text="viewMode !== mode.key"
          @click="viewMode = mode.key"
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

    <div
      v-else
      class="agenda-fc-theme bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-2 sm:p-4"
    >
      <FullCalendar :key="viewMode" :options="calendarOptions">
        <template #eventContent="arg">
          <AgendaEventChip :item="arg.event.extendedProps.item" :time-text="arg.timeText" :view-type="arg.view.type" />
        </template>
      </FullCalendar>
    </div>

    <AgendaEntryDrawer
      v-model:visible="dialogVisible"
      :bandSpaceId="route.params.id"
      :agendaItem="dialogItem"
      :initialDatetime="dialogInitialDatetime"
    />
    <ConfirmDialog />
  </div>
</template>

<script setup>
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import timeGridPlugin from '@fullcalendar/timegrid'
import FullCalendar from '@fullcalendar/vue3'
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
import ConfirmDialog from 'primevue/confirmdialog'
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

const dialogVisible = ref(false)
const dialogItem = ref(null)
const dialogInitialDatetime = ref(null)

const today = startOfDay(new Date())
const dateFrom = ref(today)
const dateTo = ref(addDays(today, 30))

const viewMode = ref('list')
const viewModeOptions = [
  { key: 'list', label: 'Liste' },
  { key: 'dayGridMonth', label: 'Mois' },
  { key: 'timeGridWeek', label: 'Semaine' },
  { key: 'timeGridDay', label: 'Jour' }
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
  manual: { bg: '#3b82f6', border: '#2563eb' },
  task: { bg: '#f59e0b', border: '#d97706' },
  finance: { bg: '#10b981', border: '#059669' }
}

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

const calendarEvents = computed(() =>
  filteredItems.value.map((item) => {
    const colors = SOURCE_COLORS[item.source] ?? { bg: '#94a3b8', border: '#64748b' }
    return {
      id: item.id,
      title: item.title,
      start: item.datetime,
      end: calendarEnd(item),
      allDay: isAllDayItem(item),
      backgroundColor: colors.bg,
      borderColor: colors.border,
      classNames: ['agenda-event-clickable'],
      extendedProps: { item }
    }
  })
)

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  initialView: viewMode.value === 'list' ? 'dayGridMonth' : viewMode.value,
  initialDate: dateFrom.value,
  locale: 'fr',
  firstDay: 1,
  events: calendarEvents.value,
  eventClick: handleEventClick,
  dateClick: handleDateClick,
  datesSet: handleDatesSet,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: ''
  },
  buttonText: {
    today: "Aujourd'hui"
  },
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

watch(
  () => route.params.id,
  (newId, oldId) => {
    if (newId && newId !== oldId) {
      agendaStore.clear()
      fetchWithCurrentRange()
    }
  }
)

function fetchWithCurrentRange() {
  const fromIso = format(dateFrom.value, "yyyy-MM-dd'T'00:00:00")
  const toIso = format(dateTo.value, "yyyy-MM-dd'T'23:59:59")
  agendaStore.fetchAgenda(route.params.id, { from: fromIso, to: toIso })
}

function handleDateRangeApply({ from, to }) {
  dateFrom.value = from
  dateTo.value = to
  fetchWithCurrentRange()
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
  return format(addDays(parseISO(item.end_datetime), 1), "yyyy-MM-dd")
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
.agenda-fc-theme .fc {
  --fc-border-color: var(--p-surface-200);
  --fc-page-bg-color: transparent;
  --fc-neutral-bg-color: var(--p-surface-50);
  --fc-today-bg-color: color-mix(in oklch, var(--p-primary-color) 8%, transparent);
  --fc-event-text-color: #fff;
  color: var(--p-text-color);
}

.agenda-fc-theme .fc-col-header-cell-cushion,
.agenda-fc-theme .fc-daygrid-day-number,
.agenda-fc-theme .fc-timegrid-axis-cushion,
.agenda-fc-theme .fc-timegrid-slot-label-cushion,
.agenda-fc-theme .fc-list-day-cushion {
  color: var(--p-text-color);
}

.agenda-fc-theme .fc-toolbar-title {
  color: var(--p-text-color);
}

.agenda-fc-theme .fc-day-other .fc-daygrid-day-number {
  color: var(--p-text-muted-color);
  opacity: 0.6;
}

.dark-mode .agenda-fc-theme .fc {
  --fc-border-color: var(--p-surface-700);
  --fc-neutral-bg-color: var(--p-surface-900);
}

.agenda-fc-theme .fc-event.agenda-event-clickable {
  cursor: pointer;
}
</style>
