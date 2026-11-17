<template>
  <div class="flex flex-col gap-5 max-w-6xl w-full mx-auto">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-0">
          {{ t('search.teacher.heading') }}
        </h1>
        <p class="mt-1 text-surface-600 dark:text-surface-400">{{ t('search.teacher.intro') }}</p>
      </div>
      <!-- At the top: a teacher checking who else teaches here sees it without scrolling past every result. -->
      <p class="text-sm text-surface-600 dark:text-surface-400">
        {{ t('search.teacher.teacher_cta') }}
        <router-link :to="{ name: 'app_user_services' }" class="text-primary-700 dark:text-primary-300 font-medium hover:underline">
          {{ t('search.teacher.teacher_cta_link') }}
        </router-link>
      </p>
    </div>

    <!-- The filters search on their own as they change (#1075). -->
    <section
      :aria-label="t('search.teacher.filters_label')"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 sm:p-5 flex flex-wrap items-center gap-3"
    >
      <div class="grid grid-cols-2 lg:flex lg:flex-wrap gap-2.5 w-full lg:w-auto">
        <FloatLabel variant="in" class="col-span-2 sm:col-span-1">
          <Select
            v-model="filters.instrument"
            input-id="teacher-search-instrument"
            :options="teacherSearchStore.instruments"
            option-label="name"
            data-key="id"
            filter
            show-clear
            class="w-full lg:w-44"
          />
          <label for="teacher-search-instrument">{{ t('search.teacher.instrument') }}</label>
        </FloatLabel>

        <div class="col-span-2 sm:col-span-1">
          <button
            type="button"
            class="p-select w-full lg:w-48 h-full min-h-[3.25rem] flex items-center justify-between gap-2 px-3 text-left"
            :aria-expanded="isCityPanelOpen"
            aria-haspopup="dialog"
            @click="toggleCityPanel"
          >
            <span class="flex flex-col min-w-0">
              <span class="text-xs text-surface-500 dark:text-surface-400">{{ t('search.teacher.where') }}</span>
              <span class="truncate text-surface-900 dark:text-surface-0">{{ whereLabel }}</span>
            </span>
            <i class="pi pi-chevron-down text-surface-500 text-sm" aria-hidden="true" />
          </button>
          <Popover ref="cityPanel" @show="isCityPanelOpen = true" @hide="handleCityPanelHide">
            <div class="flex flex-col gap-3 w-72">
              <label for="teacher-search-city" class="text-sm font-medium">{{ t('search.teacher.where') }}</label>
              <CityAutoComplete
                ref="cityField"
                v-model="cityDraft"
                input-id="teacher-search-city"
                :placeholder="t('search.teacher.city_placeholder')"
                @blur="cityField?.pickFirstSuggestion()"
              />
              <span id="teacher-search-radius" class="text-sm font-medium">{{ t('search.teacher.radius') }}</span>
              <SelectButton
                v-model="filters.radius"
                :options="radiusOptions"
                option-label="label"
                option-value="value"
                :allow-empty="false"
                aria-labelledby="teacher-search-radius"
                class="w-full [&_.p-togglebutton]:flex-1"
              />
              <Button
                v-if="filters.city"
                :label="t('search.teacher.clear_city')"
                icon="pi pi-times"
                severity="secondary"
                text
                size="small"
                class="self-start"
                @click="clearCity"
              />
            </div>
          </Popover>
        </div>

        <FloatLabel variant="in">
          <Select
            v-model="filters.level"
            input-id="teacher-search-level"
            :options="levelOptions"
            option-label="label"
            option-value="value"
            show-clear
            class="w-full lg:w-40"
          />
          <label for="teacher-search-level">{{ t('search.teacher.level') }}</label>
        </FloatLabel>

        <FloatLabel variant="in">
          <Select
            v-model="filters.ageGroup"
            input-id="teacher-search-age-group"
            :options="ageGroupOptions"
            option-label="label"
            option-value="value"
            show-clear
            class="w-full lg:w-40"
          />
          <label for="teacher-search-age-group">{{ t('search.teacher.age_group') }}</label>
        </FloatLabel>
      </div>

      <div class="flex flex-wrap items-center gap-x-5 gap-y-3 lg:ml-2">
        <!-- Widens a search around a city; without one every teacher is already found. -->
        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
          <ToggleSwitch v-model="filters.online" />
          {{ t('search.teacher.online') }}
        </label>
        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
          <ToggleSwitch v-model="filters.freeTrial" />
          {{ t('search.teacher.free_trial') }}
        </label>
      </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
      <h2 class="font-semibold text-surface-900 dark:text-surface-0" aria-live="polite">
        {{ resultsHeading }}
      </h2>
      <div class="flex items-center gap-2 ml-auto">
        <span id="teacher-search-sort" class="text-sm text-surface-600 dark:text-surface-400">{{ t('search.teacher.sort_label') }}</span>
        <SelectButton
          :model-value="shownOrder"
          :options="orderOptions"
          option-label="label"
          option-value="value"
          :allow-empty="false"
          aria-labelledby="teacher-search-sort"
          size="small"
          @update:model-value="filters.order = $event"
        />
      </div>
    </div>

    <Message v-if="hasError" severity="error" :closable="false">{{ t('search.teacher.error') }}</Message>

    <div v-if="isLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Skeleton v-for="n in 4" :key="n" height="13rem" border-radius="1rem" />
    </div>

    <template v-else-if="teacherSearchStore.teachers.length">
      <div
        class="grid grid-cols-1 lg:grid-cols-2 gap-4 transition-opacity"
        :class="{ 'opacity-60': isRefreshing }"
        :aria-busy="isRefreshing"
      >
        <TeacherSearchCard
          v-for="teacher in teacherSearchStore.teachers"
          :key="teacher.username"
          :teacher="teacher"
          :is-playing="playingUsername === teacher.username"
          @toggle-listen="toggleListen(teacher.username)"
        />
      </div>
      <div v-if="teacherSearchStore.hasMore" class="flex justify-center">
        <Button
          :label="t('search.teacher.load_more')"
          :loading="isLoadingMore"
          severity="secondary"
          outlined
          @click="loadMore"
        />
      </div>
    </template>

    <div
      v-else-if="!hasError"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-8 text-center flex flex-col items-center gap-3"
    >
      <i class="pi pi-search text-3xl text-surface-400" aria-hidden="true" />
      <p class="font-semibold text-surface-900 dark:text-surface-0">{{ t('search.teacher.empty_title') }}</p>
      <p class="text-surface-600 dark:text-surface-400">{{ t('search.teacher.empty_text') }}</p>
      <Button
        v-if="hasFilters"
        :label="t('search.teacher.clear_filters')"
        severity="secondary"
        outlined
        size="small"
        @click="clearFilters"
      />
    </div>
  </div>
</template>

<script setup>
defineOptions({ name: 'TeacherSearch' })

import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import FloatLabel from 'primevue/floatlabel'
import Message from 'primevue/message'
import Popover from 'primevue/popover'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Skeleton from 'primevue/skeleton'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onActivated, onDeactivated, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import CityAutoComplete from '../../components/Global/CityAutoComplete.vue'
import { getAgeGroupLabel, getStudentLevelLabel } from '../../constants/teacherProfile.js'
import { intlLocale } from '../../i18n/formatLocale.js'
import { useTeacherSearchStore } from '../../store/search/teacher.js'
import { createSearchAutoRun } from '../../utils/searchAutoRun.js'
import {
  effectiveOrder,
  emptyTeacherFilters,
  instrumentOf,
  TEACHER_AGE_GROUPS,
  TEACHER_LEVELS,
  TEACHER_ORDERS,
  TEACHER_RADII,
  teacherFiltersFromUrlQuery,
  teacherSearchParams,
  teacherSearchUrlQuery
} from '../../utils/teacherSearch.js'
import TeacherSearchCard from './TeacherSearchCard.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const teacherSearchStore = useTeacherSearchStore()

const ORDER_LABEL_KEYS = {
  distance: 'search.teacher.sort.distance',
  price: 'search.teacher.sort.price',
  free_trial: 'search.teacher.sort.free_trial'
}
// By whether an instrument, then a city, is searched.
const COUNT_KEYS = {
  none_none: 'search.teacher.count',
  instrument_none: 'search.teacher.count_instrument',
  none_city: 'search.teacher.count_city',
  instrument_city: 'search.teacher.count_instrument_city'
}
const TITLE_KEYS = {
  none_none: 'search.teacher.title',
  instrument_none: 'search.teacher.title_instrument',
  none_city: 'search.teacher.title_city',
  instrument_city: 'search.teacher.title_instrument_city'
}

const filters = reactive(emptyTeacherFilters())
// The city field's own value: the typed text until a city is picked.
const cityDraft = ref(null)
const cityField = ref(null)
const cityPanel = ref(null)
const isCityPanelOpen = ref(false)

const isLoading = ref(true)
const isRefreshing = ref(false)
const isLoadingMore = ref(false)
const hasError = ref(false)
const playingUsername = ref(null)

const radiusOptions = computed(() =>
  TEACHER_RADII.map((radius) => ({
    value: radius,
    label: t('search.teacher.radius_option', { radius })
  }))
)
const levelOptions = computed(() =>
  TEACHER_LEVELS.map((level) => ({ value: level, label: getStudentLevelLabel(level) }))
)
const ageGroupOptions = computed(() =>
  TEACHER_AGE_GROUPS.map((group) => ({ value: group, label: getAgeGroupLabel(group) }))
)
// The nearest first only once there is a city: a disabled button looked selected (#1314).
const orderOptions = computed(() =>
  TEACHER_ORDERS.filter((order) => order !== 'distance' || filters.city).map((order) => ({
    value: order,
    label: t(ORDER_LABEL_KEYS[order])
  }))
)
// No city and no order asked: the newest first, which no button names.
const shownOrder = computed(() => effectiveOrder(filters.order, !!filters.city))

const whereLabel = computed(() =>
  filters.city
    ? t('search.teacher.where_value', { city: filters.city.name, radius: filters.radius })
    : t('search.teacher.anywhere')
)

/**
 * Which of the instrument and the city are searched, and how a sentence names them: the heading and the title read the
 * way the server's meta does (TeacherSearchMetaDataProvider).
 */
const landing = computed(() => {
  const name = filters.instrument?.name
  return {
    variant: `${name ? 'instrument' : 'none'}_${filters.city ? 'city' : 'none'}`,
    params: { instrument: name ? instrumentOf(name, intlLocale()) : null, city: filters.city?.name }
  }
})

const resultsHeading = computed(() => {
  const count = teacherSearchStore.totalItems
  return t(COUNT_KEYS[landing.value.variant], { ...landing.value.params, count }, count)
})

// English names the instrument first: « Bass teacher », not « bass teacher ».
const capitalized = (text) => text.charAt(0).toLocaleUpperCase(intlLocale()) + text.slice(1)

const pageTitle = computed(() =>
  capitalized(t(TITLE_KEYS[landing.value.variant], landing.value.params))
)
// Not put back on unmount: the cached page unmounts after the next page has set its own title.
const documentTitle = useTitle(pageTitle, { restoreOnUnmount: false })

// --- Searching -------------------------------------------------------------------------------------------

const autoRun = createSearchAutoRun({ refresh: refreshResults, record: recordSettledSearch })
const filtersKey = computed(() => JSON.stringify(teacherSearchParams(filters)))
const hasFilters = computed(() => filtersKey.value !== '{}')
// What the list on screen was searched with: filling the filters from the address searches right after.
let searchedKey = null
// Only the latest refresh lifts the dimming, as only its answer shows.
let refreshToken = 0
// Kept alive while a visitor opens a teacher: a hidden search must not search, nor write its filters onto
// another page's address.
const isActive = ref(true)

onActivated(() => {
  isActive.value = true
  // Back from a teacher, whose page set its own title while this one was hidden.
  documentTitle.value = pageTitle.value
})
onDeactivated(() => {
  isActive.value = false
  autoRun.cancel()
  playingUsername.value = null
})

onMounted(async () => {
  const instrumentsLoaded = teacherSearchStore.loadInstruments().catch(() => {
    // Without the list the instrument filter is empty; the search itself still works.
  })
  // Only an instrument in the address needs the list before searching: its slug becomes an id.
  if (route.query.instrument) await instrumentsLoaded
  Object.assign(filters, teacherFiltersFromUrlQuery(route.query, teacherSearchStore.instruments))
  cityDraft.value = filters.city
  searchedKey = filtersKey.value
  try {
    await teacherSearchStore.search(teacherSearchParams(filters))
  } catch {
    hasError.value = true
  }
  isLoading.value = false
})

async function refreshResults() {
  const token = ++refreshToken
  searchedKey = filtersKey.value
  isRefreshing.value = true
  hasError.value = false
  let applied = false
  try {
    applied = await teacherSearchStore.search(teacherSearchParams(filters))
  } catch (error) {
    if (token === refreshToken) hasError.value = true
    throw error
  } finally {
    if (token === refreshToken) isRefreshing.value = false
  }
  if (!applied || !isActive.value) return
  playingUsername.value = null
  router.replace({ query: teacherSearchUrlQuery(filters) })
}

function recordSettledSearch() {
  trackUmamiEvent('teacher-search', {
    instrument: filters.instrument?.slug ?? null,
    city: filters.city?.name ?? null,
    results: teacherSearchStore.totalItems
  })
}

watch(filtersKey, (key) => {
  if (!isActive.value || isLoading.value || key === searchedKey) return
  autoRun.changed()
})

async function loadMore() {
  // The page after the one on screen, so a refresh still on its way lands first.
  await autoRun.settleNow()
  isLoadingMore.value = true
  try {
    await teacherSearchStore.search(
      teacherSearchParams(filters, teacherSearchStore.currentPage + 1)
    )
  } catch {
    hasError.value = true
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  Object.assign(filters, emptyTeacherFilters())
  cityDraft.value = null
}

// --- The city -------------------------------------------------------------------------------------------

function toggleCityPanel(event) {
  cityPanel.value.toggle(event)
}

// A city counts once picked, not while it is typed.
watch(cityDraft, (city) => {
  if (city && typeof city === 'object') filters.city = city
  else if (!city) filters.city = null
})

async function handleCityPanelHide() {
  isCityPanelOpen.value = false
  await cityField.value?.pickFirstSuggestion()
}

function clearCity() {
  cityDraft.value = null
  cityPanel.value.hide()
}

function toggleListen(username) {
  playingUsername.value = playingUsername.value === username ? null : username
}
</script>
