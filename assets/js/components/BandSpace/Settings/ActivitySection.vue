<template>
  <div class="flex flex-col gap-4">
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6">
      <h2 class="text-lg font-semibold text-surface-700 dark:text-surface-200 mb-4">
        Journal d'activité
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <MultiSelect
          v-model="localFilters.modules"
          :options="MODULE_OPTIONS"
          option-label="label"
          option-value="value"
          placeholder="Tous les modules"
          display="chip"
          class="w-full"
        />
        <Select
          v-if="isAdmin"
          v-model="localFilters.actorId"
          :options="memberOptions"
          option-label="label"
          option-value="value"
          placeholder="Tous les auteurs"
          show-clear
          class="w-full"
        />
        <DatePicker
          v-model="localFilters.from"
          placeholder="Du..."
          show-button-bar
          date-format="dd/mm/yy"
          class="w-full"
        />
        <DatePicker
          v-model="localFilters.to"
          placeholder="Au..."
          show-button-bar
          date-format="dd/mm/yy"
          class="w-full"
        />
      </div>

      <div class="flex justify-end mb-2">
        <Button
          label="Réinitialiser"
          severity="secondary"
          text
          size="small"
          @click="handleReset"
        />
      </div>

      <div v-if="store.isLoading" class="text-center text-sm text-surface-500 py-8">
        Chargement…
      </div>

      <div
        v-else-if="store.items.length === 0"
        class="text-center text-sm text-surface-500 py-8"
      >
        Aucune activité ne correspond à ces filtres.
      </div>

      <div v-else class="flex flex-col gap-1">
        <div
          v-for="activity in store.items"
          :key="activity.id"
          :class="[
            'flex items-start gap-3 p-3 rounded-lg',
            isClickable(activity)
              ? 'cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-800'
              : ''
          ]"
          @click="handleClick(activity)"
        >
          <Avatar
            :username="activity.actor?.username || 'Système'"
            :picture-url="activity.actor?.profile_picture_url"
            size="sm"
          />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-medium text-sm text-surface-700 dark:text-surface-200">
                {{ activity.actor?.username || 'Système' }}
              </span>
              <Tag
                :value="moduleLabel(activity.module)"
                :severity="moduleSeverity(activity.module)"
                class="text-xs"
              />
              <span class="text-sm text-surface-600 dark:text-surface-300">
                {{ activitySentence(activity) }}
              </span>
            </div>
            <div class="text-xs text-surface-400 mt-0.5">
              {{ formatRelative(activity.creation_datetime) }}
            </div>
          </div>
        </div>

        <div v-if="store.items.length < store.totalItems" class="flex justify-center mt-4">
          <Button
            label="Charger plus"
            :loading="store.isLoadingMore"
            severity="secondary"
            outlined
            @click="handleLoadMore"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import { fr } from 'date-fns/locale'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, onMounted, onUnmounted, reactive, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandSpaceActivityStore } from '../../../store/bandSpace/bandSpaceActivity.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import Avatar from '../../User/Avatar.vue'
import { activitySentence as buildSentence } from './activitySentences.js'

const route = useRoute()
const router = useRouter()
const store = useBandSpaceActivityStore()
const settingsStore = useBandSpaceSettingsStore()
// Wipe previous space's activity feed synchronously before first render to
// avoid flashing A's events when switching to B's Settings.
store.clear()

const bandSpaceId = route.params.id

// The author filter needs the member list, which is admin-only; members get the feed without it.
const { currentSpace } = useBandSpaceNavigation()
const isAdmin = computed(() => currentSpace.value?.role === 'admin')

const MODULE_OPTIONS = [
  { value: 'task', label: 'Tâches' },
  { value: 'finance', label: 'Finances' },
  { value: 'agenda', label: 'Agenda' },
  { value: 'notes', label: 'Notes' },
  { value: 'file', label: 'Fichiers' },
  { value: 'setlist', label: 'Setlists' },
  { value: 'settings', label: 'Paramètres' }
]

const localFilters = reactive({
  modules: [],
  actorId: null,
  from: null,
  to: null
})

const memberOptions = computed(() =>
  (settingsStore.members ?? []).map((m) => ({
    value: m.user_id,
    label: m.username
  }))
)

function moduleLabel(module) {
  return MODULE_OPTIONS.find((o) => o.value === module)?.label ?? module
}

function moduleSeverity(module) {
  const map = {
    task: 'info',
    finance: 'success',
    agenda: 'warn',
    notes: 'secondary',
    file: 'help',
    settings: 'contrast'
  }
  return map[module] ?? 'secondary'
}

function activitySentence(activity) {
  return buildSentence(activity)
}

function formatRelative(dateStr) {
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: fr })
}

function isClickable(activity) {
  return ['task', 'finance', 'agenda', 'notes'].includes(activity.module)
}

function handleClick(activity) {
  if (!isClickable(activity)) {
    return
  }

  const routeMap = {
    task: { name: 'app_band_tasks', query: { task: activity.resource_id } },
    finance: { name: 'app_band_finance' },
    agenda: { name: 'app_band_agenda' },
    notes: { name: 'app_band_notes', query: { note: activity.resource_id } }
  }

  const target = routeMap[activity.module]
  if (target) {
    router.push({ ...target, params: { id: bandSpaceId } })
  }
}

function applyFiltersToStore() {
  store.setFilters({
    modules: localFilters.modules,
    actorId: localFilters.actorId,
    from: localFilters.from ? toIsoStartOfDay(localFilters.from) : null,
    to: localFilters.to ? toIsoEndOfDay(localFilters.to) : null
  })
}

// The picked day is turned into an instant here rather than sent as a date, because only the client
// knows its own offset: "from the 5th" in Paris means 22:00 UTC on the 4th, and a server reading a
// bare `2026-09-05` as a UTC day would clip the first two hours of the day the user asked for.
// The milliseconds go because the API validates these two against ATOM, which has no place for them.
function toAtom(date) {
  return date.toISOString().replace(/\.\d{3}Z$/, 'Z')
}

function toIsoStartOfDay(date) {
  const d = new Date(date)
  d.setHours(0, 0, 0, 0)
  return toAtom(d)
}

function toIsoEndOfDay(date) {
  const d = new Date(date)
  // Seconds, not 999ms: ATOM has no place for milliseconds, so anything finer is dropped on the way
  // out anyway and writing it here would only suggest a precision the filter does not have.
  d.setHours(23, 59, 59, 0)
  return toAtom(d)
}

function handleReset() {
  localFilters.modules = []
  localFilters.actorId = null
  localFilters.from = null
  localFilters.to = null
}

async function handleLoadMore() {
  await store.loadMore(bandSpaceId)
}

watch(
  localFilters,
  () => {
    applyFiltersToStore()
    store.load(bandSpaceId)
  },
  { deep: true }
)

onMounted(async () => {
  if (isAdmin.value && settingsStore.members.length === 0) {
    await settingsStore.loadMembers(bandSpaceId)
  }
  applyFiltersToStore()
  await store.load(bandSpaceId)
})

onUnmounted(() => {
  store.clear()
})
</script>
