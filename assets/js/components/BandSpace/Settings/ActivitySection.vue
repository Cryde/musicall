<template>
  <div class="flex flex-col gap-4">
    <!-- The phone's filters: three chips above the card, opening one sheet. -->
    <div v-if="isBelowMd" class="flex flex-wrap gap-1.5">
      <button
        v-for="chip in filterChips"
        :key="chip.key"
        type="button"
        :class="[PHONE_CHIP, chip.isSet ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
        aria-haspopup="dialog"
        @click="isFilterSheetOpen = true"
      >
        <i v-if="chip.icon" class="pi text-xs" :class="chip.icon" aria-hidden="true" />
        {{ chip.label }}
        <i v-if="!chip.icon" class="pi pi-chevron-down text-xs" aria-hidden="true" />
        <span v-if="chip.isSet" class="sr-only">{{ t('band_space.settings.phone.filter_set') }}</span>
      </button>
    </div>
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 md:p-6 max-md:overflow-hidden max-md:p-0">
      <!-- On a phone the section strip above already names it. -->
      <h2 class="text-lg font-semibold text-surface-700 dark:text-surface-200 mb-4 max-md:sr-only">
        {{ t('band_space.settings.section.activity') }}
      </h2>

      <div v-if="!isBelowMd" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <MultiSelect
          v-model="localFilters.modules"
          :options="moduleOptions"
          option-label="label"
          option-value="value"
          :placeholder="t('band_space.activity_log.all_modules')"
          display="chip"
          class="w-full"
        />
        <Select
          v-if="isAdmin"
          v-model="localFilters.actorId"
          :options="memberOptions"
          option-label="label"
          option-value="value"
          :placeholder="t('band_space.activity_log.all_authors')"
          show-clear
          class="w-full"
        />
        <DatePicker
          v-model="localFilters.from"
          :placeholder="t('band_space.activity_log.from')"
          show-button-bar
          date-format="dd/mm/yy"
          class="w-full"
        />
        <DatePicker
          v-model="localFilters.to"
          :placeholder="t('band_space.activity_log.to')"
          show-button-bar
          date-format="dd/mm/yy"
          class="w-full"
        />
      </div>

      <div v-if="!isBelowMd" class="flex justify-end mb-2">
        <Button
          :label="t('band_space.activity_log.reset')"
          severity="secondary"
          text
          size="small"
          @click="handleReset"
        />
      </div>

      <div v-if="store.isLoading" class="text-center text-sm text-surface-500 py-8">
        {{ t('band_space.activity_log.loading') }}
      </div>

      <div
        v-else-if="store.items.length === 0"
        class="text-center text-sm text-surface-500 py-8"
      >
        {{ t('band_space.activity_log.empty') }}
      </div>

      <!-- On a phone one line per row across the card, a rule between them. -->
      <div v-else class="flex flex-col gap-1 max-md:gap-0 max-md:divide-y max-md:divide-surface-200 dark:max-md:divide-surface-700">
        <div
          v-for="activity in store.items"
          :key="activity.id"
          class="flex items-start gap-3 p-3 rounded-lg max-md:rounded-none max-md:px-4 max-md:py-3"
        >
          <Avatar
            class="max-md:shrink-0 max-md:!h-9 max-md:!w-9"
            :username="activity.actor?.username || t('band_space.common.system')"
            :display-name="activity.actor?.display_name"
            :picture-url="activity.actor?.profile_picture_url"
            size="sm"
          />
          <!-- A phone reads the line as a sentence, « User Admineu a retiré user_base », its module
               and time under it. -->
          <div v-if="isBelowMd" class="flex-1 min-w-0">
            <p class="m-0 text-sm text-surface-700 [overflow-wrap:anywhere] dark:text-surface-200">
              <span class="font-semibold text-surface-900 dark:text-surface-0">
                <UserName v-if="activity.actor" :username="activity.actor.username" :display-name="activity.actor.display_name" :picture-url="activity.actor.profile_picture_url" /><template v-else>{{ t('band_space.common.system') }}</template>
              </span>
              {{ ' ' }}
              <RouterLink
                v-if="activityLink(activity, bandSpaceId)"
                :to="activityLink(activity, bandSpaceId)"
                class="text-inherit hover:underline focus-visible:underline"
              >
                {{ activitySentence(activity) }}
              </RouterLink>
              <template v-else>{{ activitySentence(activity) }}</template>
            </p>
            <div class="mt-1 flex items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
              <Tag
                :value="moduleLabel(activity.module)"
                :severity="moduleSeverity(activity.module)"
                class="!rounded-md !px-[7px] !py-px !text-[0.6875rem]"
              />
              <span>{{ formatRelative(activity.creation_datetime) }}</span>
            </div>
          </div>
          <div v-else class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-medium text-sm text-surface-700 dark:text-surface-200">
                <UserName v-if="activity.actor" :username="activity.actor.username" :display-name="activity.actor.display_name" :picture-url="activity.actor.profile_picture_url" /><template v-else>{{ t('band_space.common.system') }}</template>
              </span>
              <Tag
                :value="moduleLabel(activity.module)"
                :severity="moduleSeverity(activity.module)"
                class="text-xs"
              />
              <RouterLink
                v-if="activityLink(activity, bandSpaceId)"
                :to="activityLink(activity, bandSpaceId)"
                class="text-sm text-surface-600 dark:text-surface-300 hover:underline focus-visible:underline"
              >
                {{ activitySentence(activity) }}
              </RouterLink>
              <span v-else class="text-sm text-surface-600 dark:text-surface-300">
                {{ activitySentence(activity) }}
              </span>
            </div>
            <div class="text-xs text-surface-400 mt-0.5">
              {{ formatRelative(activity.creation_datetime) }}
            </div>
          </div>
        </div>

        <div v-if="store.items.length < store.totalItems" class="flex justify-center mt-4 max-md:mt-0 max-md:py-3">
          <Button
            :label="t('band_space.activity_log.load_more')"
            :loading="store.isLoadingMore"
            severity="secondary"
            outlined
            @click="handleLoadMore"
          />
        </div>
      </div>
    </div>

    <BottomSheet v-if="isBelowMd" v-model:visible="isFilterSheetOpen" :title="t('band_space.settings.phone.filters')">
      <template #header>
        <span class="flex min-w-0 flex-1 items-center justify-between gap-2">
          <span class="text-lg font-semibold text-surface-900 dark:text-surface-0">
            {{ t('band_space.settings.phone.filters') }}
          </span>
          <Button :label="t('band_space.activity_log.reset')" text size="small" @click="handleReset" />
        </span>
      </template>

      <div class="flex flex-col gap-4 px-5 pb-2">
        <fieldset class="m-0 border-0 p-0">
          <legend class="mb-2 text-sm font-medium">{{ t('band_space.settings.phone.modules_chip') }}</legend>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="option in moduleOptions"
              :key="option.value"
              type="button"
              :class="[PHONE_CHIP, localFilters.modules.includes(option.value) ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
              :aria-pressed="localFilters.modules.includes(option.value)"
              @click="toggleModule(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </fieldset>

        <fieldset v-if="isAdmin" class="m-0 border-0 p-0">
          <legend class="mb-2 text-sm font-medium">{{ t('band_space.settings.phone.authors_chip') }}</legend>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="option in memberOptions"
              :key="option.value"
              type="button"
              :class="[PHONE_CHIP, localFilters.actorId === option.value ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
              :aria-pressed="localFilters.actorId === option.value"
              @click="toggleAuthor(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </fieldset>

        <fieldset class="m-0 border-0 p-0">
          <legend class="mb-2 text-sm font-medium">{{ t('band_space.settings.phone.dates_chip') }}</legend>
          <div class="grid grid-cols-2 gap-3">
            <DatePicker
              v-model="localFilters.from"
              :placeholder="t('band_space.activity_log.from')"
              :aria-label="t('band_space.activity_log.from')"
              date-format="d M yy"
              :manual-input="false"
              show-button-bar
              fluid
            />
            <DatePicker
              v-model="localFilters.to"
              :placeholder="t('band_space.activity_log.to')"
              :aria-label="t('band_space.activity_log.to')"
              date-format="d M yy"
              :manual-input="false"
              show-button-bar
              fluid
            />
          </div>
        </fieldset>

        <Button :label="t('band_space.settings.phone.show_activity')" @click="isFilterSheetOpen = false" />
      </div>
    </BottomSheet>
  </div>
</template>

<script setup>
import { formatDistanceToNow } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { PHONE_CHIP, PHONE_CHIP_OFF, PHONE_CHIP_ON } from '../../../constants/phoneControls.js'
import { dateFnsLocale } from '../../../i18n/formatLocale.js'
import { useBandSpaceActivityStore } from '../../../store/bandSpace/bandSpaceActivity.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import { activityLink } from '../../../utils/bandSpaceActivityLink.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import Avatar from '../../User/Avatar.vue'
import UserName from '../../User/UserName.vue'
import { activitySentence as buildSentence } from './activitySentences.js'

const route = useRoute()
const store = useBandSpaceActivityStore()
const settingsStore = useBandSpaceSettingsStore()
const { t } = useI18n()
// Wipe previous space's activity feed synchronously before first render to
// avoid flashing A's events when switching to B's Settings.
store.clear()

const bandSpaceId = route.params.id

// The author filter needs the member list, which is admin-only; members get the feed without it.
const { currentSpace } = useBandSpaceNavigation()
const isAdmin = computed(() => currentSpace.value?.role === 'admin')

const moduleOptions = computed(() => [
  { value: 'task', label: t('band_space.module_name.tasks') },
  { value: 'finance', label: t('band_space.module_name.finance') },
  { value: 'agenda', label: t('band_space.module_name.agenda') },
  { value: 'notes', label: t('band_space.module_name.notes') },
  { value: 'file', label: t('band_space.module_name.files') },
  { value: 'setlist', label: t('band_space.module_name.setlist') },
  { value: 'settings', label: t('band_space.module_name.parameters') }
])

const localFilters = reactive({
  modules: [],
  actorId: null,
  from: null,
  to: null
})

const memberOptions = computed(() =>
  (settingsStore.members ?? []).map((m) => ({
    value: m.user_id,
    label: m.display_name
  }))
)

const isBelowMd = useIsBelowMd()
const isFilterSheetOpen = ref(false)
// The sheet only exists below md: left open across a resize, it would pop up again on the way back.
watch(isBelowMd, () => {
  isFilterSheetOpen.value = false
})

// The phone's three chips, lit when their filter is set; all three open the same sheet.
const filterChips = computed(() => {
  const moduleCount = localFilters.modules.length
  const author = memberOptions.value.find((option) => option.value === localFilters.actorId)
  const chips = [
    {
      key: 'modules',
      label: moduleCount
        ? `${t('band_space.settings.phone.modules_chip')} · ${moduleCount}`
        : t('band_space.settings.phone.modules_chip'),
      isSet: moduleCount > 0
    }
  ]
  if (isAdmin.value) {
    chips.push({
      key: 'authors',
      label: author?.label ?? t('band_space.settings.phone.authors_chip'),
      isSet: Boolean(author)
    })
  }
  chips.push({
    key: 'dates',
    icon: 'pi-calendar',
    label: t('band_space.settings.phone.dates_chip'),
    isSet: Boolean(localFilters.from || localFilters.to)
  })

  return chips
})

function toggleModule(module) {
  localFilters.modules = localFilters.modules.includes(module)
    ? localFilters.modules.filter((value) => value !== module)
    : [...localFilters.modules, module]
}

// One author at a time, as the desktop's Select; a second tap clears it.
function toggleAuthor(actorId) {
  localFilters.actorId = localFilters.actorId === actorId ? null : actorId
}

function moduleLabel(module) {
  return moduleOptions.value.find((o) => o.value === module)?.label ?? module
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
  return formatDistanceToNow(new Date(dateStr), { addSuffix: true, locale: dateFnsLocale() })
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
