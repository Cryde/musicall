<template>
  <div class="flex flex-col gap-4">
    <BandHomeHeader :member-count="members.data?.length ?? 0" />

    <BandHomeNextDate
      :band-space-id="bandSpaceId"
      :next="upcoming[0] ?? null"
      :last-past="agenda.data?.lastPast ?? null"
      :is-loading="agenda.isLoading"
      :failed="agenda.failed"
      :now="now"
      @answered="handleAnswered"
    />

    <BandHomeChatRow :band-space-id="bandSpaceId" :now="now" />

    <nav :aria-label="t('band_space.sidebar.modules_aria')" class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
      <BandHomeTile v-for="tile in tiles" :key="tile.key" v-bind="tile" />
    </nav>

    <ActivityWidget :band-space-id="bandSpaceId" :limit="3" />
    <MobileAppWidget />
  </div>
</template>

<script setup>
import { useNow } from '@vueuse/core'
import { addYears, endOfMonth, format, startOfMonth, subYears } from 'date-fns'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import bandSpaceFinanceApi from '../../../api/bandSpace/band-space-finance.js'
import bandSpaceNotesApi from '../../../api/bandSpace/band-space-notes.js'
import bandSpaceSetlistsApi from '../../../api/bandSpace/band-space-setlists.js'
import bandSpaceSettingsApi from '../../../api/bandSpace/band-space-settings.js'
import bandSpaceTasksApi from '../../../api/bandSpace/band-space-tasks.js'
import bandSpaceTechRidersApi from '../../../api/bandSpace/band-space-tech-riders.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandSpaceResource } from '../../../composables/useBandSpaceResource.js'
import { BAND_SPACE_ROUTES, RIDER_TESTER_ONLY } from '../../../constants/bandSpace.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { withAvailabilityAnswer } from '../../../utils/agendaAvailability.js'
import {
  agendaStatus,
  fileStatus,
  financeStatus,
  lastPastDate,
  memberStatus,
  noteStatus,
  setlistStatus,
  taskStatus,
  techRiderStatus,
  upcomingDates
} from '../../../utils/bandHomeStatus.js'
import ActivityWidget from './ActivityWidget.vue'
import BandHomeChatRow from './BandHomeChatRow.vue'
import BandHomeHeader from './BandHomeHeader.vue'
import BandHomeNextDate from './BandHomeNextDate.vue'
import BandHomeTile from './BandHomeTile.vue'
import MobileAppWidget from './MobileAppWidget.vue'

/**
 * The dashboard below `lg`, where the sidebar is hidden: the Android app's band home (screen 01 of
 * Cryde/musicall-app). The band, its next date and its chat, then one tile per module saying what is
 * going on in it. Each tile reads its own sources, so a request that fails costs that tile only.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const { currentSpace } = useBandSpaceNavigation()

const isAdmin = computed(() => currentSpace.value?.role === 'admin')
// As the sidebar: a module merged but not yet announced shows to testers only.
const showsRiders = computed(() => !RIDER_TESTER_ONLY || userSecurityStore.isTester)

// A getter, so the reads follow the prop; named apart so the template keeps reading the prop.
const spaceId = () => props.bandSpaceId

// A page left open still drops a date once it has started, and ages « Modifiée il y a 5 min ».
const now = useNow({ interval: 60 * 1000 })

function day(date) {
  return format(date, 'yyyy-MM-dd')
}

// A year ahead, as the app reads it. Only a band with nothing planned also says when it last met; that
// line is optional, so its failure costs nothing more.
async function loadAgenda(id) {
  const today = new Date()
  const items = await bandSpaceAgendaApi.getAgenda(id, {
    from: day(today),
    to: day(addYears(today, 1))
  })
  if (upcomingDates(items).length > 0) return { items, lastPast: null }

  try {
    const past = await bandSpaceAgendaApi.getAgenda(id, {
      from: day(subYears(today, 1)),
      to: day(today)
    })
    return { items, lastPast: lastPastDate(past) }
  } catch {
    return { items, lastPast: null }
  }
}

function loadMonthSummary(id) {
  const today = new Date()
  return bandSpaceFinanceApi.getSummary(id, day(startOfMonth(today)), day(endOfMonth(today)))
}

const agenda = useBandSpaceResource({ bandSpaceId: spaceId, modules: ['agenda'], load: loadAgenda })
const taskStats = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['task'],
  load: (id) => bandSpaceTasksApi.getStats(id)
})
const taskList = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['task'],
  load: (id) => bandSpaceTasksApi.getTasks(id)
})
const setlists = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['setlist'],
  load: (id) => bandSpaceSetlistsApi.getSetlists(id)
})
const riders = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['rider'],
  load: (id) => bandSpaceTechRidersApi.getTechRiders(id),
  enabled: showsRiders
})
// One row is enough: the count is the collection's total. No folder, so every file of the space,
// attached ones included, where the Files screen opens on the root only.
const fileCount = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['file'],
  load: async (id) => (await bandSpaceFilesApi.getFiles(id, { itemsPerPage: 1 })).totalItems ?? 0
})
const quota = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['file'],
  load: (id) => bandSpaceFilesApi.getQuota(id)
})
const finance = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['finance'],
  load: loadMonthSummary
})
const notes = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['notes'],
  load: (id) => bandSpaceNotesApi.getNotes(id)
})
const members = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['settings'],
  load: (id) => bandSpaceSettingsApi.getMembers(id)
})
const invitations = useBandSpaceResource({
  bandSpaceId: spaceId,
  modules: ['settings'],
  load: (id) => bandSpaceSettingsApi.getInvitations(id),
  enabled: isAdmin
})

const upcoming = computed(() => (agenda.data ? upcomingDates(agenda.data.items, now.value) : []))

// The answer the server returned, over any read of the agenda still on its way.
function handleAnswered({ entryId, occurrenceDate, availability }) {
  agenda.set({
    ...agenda.data,
    items: withAvailabilityAnswer(agenda.data.items, entryId, occurrenceDate, availability)
  })
}

/**
 * The first source is the one the tile cannot do without: when it fails, the tile shows its name
 * only. The others add a line, and their failure costs that line.
 */
function tile(key, routeName, icon, label, sources, statusOf, query = undefined) {
  const [main] = sources
  const isLoading = sources.some((source) => source.isLoading)
  return {
    key,
    to: { name: routeName, params: { id: props.bandSpaceId }, query },
    icon,
    label,
    isLoading,
    status: isLoading || main.failed ? null : statusOf()
  }
}

const tiles = computed(() =>
  [
    tile(
      'agenda',
      BAND_SPACE_ROUTES.AGENDA,
      'pi-calendar',
      t('band_space.module_name.agenda'),
      [agenda],
      () => agendaStatus(upcoming.value, agenda.data?.lastPast ?? null)
    ),
    tile(
      'tasks',
      BAND_SPACE_ROUTES.TASKS,
      'pi-check-square',
      t('band_space.module_name.tasks'),
      [taskStats, taskList],
      () => taskStatus(taskStats.data, taskList.data, userSecurityStore.userProfile?.id ?? null)
    ),
    tile(
      'setlists',
      BAND_SPACE_ROUTES.SETLIST,
      'pi-list',
      t('band_space.module_name.setlist'),
      [setlists],
      () => setlistStatus(setlists.data ?? [])
    ),
    showsRiders.value
      ? tile(
          'riders',
          BAND_SPACE_ROUTES.RIDER,
          'pi-sliders-h',
          t('band_space.module_name.rider'),
          [riders],
          () => techRiderStatus(riders.data ?? [], now.value)
        )
      : null,
    tile(
      'files',
      BAND_SPACE_ROUTES.FILES,
      'pi-folder',
      t('band_space.module_name.files'),
      [fileCount, quota],
      () => fileStatus(fileCount.data ?? 0, quota.data)
    ),
    tile(
      'finances',
      BAND_SPACE_ROUTES.FINANCE,
      'pi-wallet',
      t('band_space.module_name.finance'),
      [finance],
      () => financeStatus(finance.data)
    ),
    tile(
      'notes',
      BAND_SPACE_ROUTES.NOTES,
      'pi-file-edit',
      t('band_space.module_name.notes'),
      [notes],
      () => noteStatus(notes.data ?? [], now.value)
    ),
    tile(
      'members',
      BAND_SPACE_ROUTES.PARAMETERS,
      'pi-users',
      t('band_space.settings.section.members'),
      [members, invitations],
      () => memberStatus(members.data ?? [], isAdmin.value ? invitations.data : null),
      { section: 'members' }
    )
  ].filter(Boolean)
)
</script>
