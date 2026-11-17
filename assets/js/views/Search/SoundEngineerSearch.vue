<template>
  <div class="flex flex-col gap-5 max-w-6xl w-full mx-auto">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-0">
          {{ t('search.sound_engineer.heading') }}
        </h1>
        <p class="mt-1 text-surface-600 dark:text-surface-400">{{ t('search.sound_engineer.intro') }}</p>
      </div>
      <p class="text-sm text-surface-600 dark:text-surface-400">
        {{ t('search.sound_engineer.engineer_cta') }}
        <router-link :to="{ name: 'app_user_services' }" class="text-primary-700 dark:text-primary-300 font-medium hover:underline">
          {{ t('search.sound_engineer.engineer_cta_link') }}
        </router-link>
      </p>
    </div>

    <!-- The filters search on their own as they change. -->
    <section
      :aria-label="t('search.sound_engineer.filters_label')"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 sm:p-5 flex flex-wrap items-center gap-3"
    >
      <div class="grid grid-cols-2 lg:flex lg:flex-wrap gap-2.5 w-full lg:w-auto">
        <FloatLabel variant="in" class="col-span-2 sm:col-span-1">
          <Select
            v-model="filters.speciality"
            input-id="sound-engineer-search-speciality"
            :options="specialityOptions"
            option-label="label"
            option-value="value"
            show-clear
            class="w-full lg:w-48"
          />
          <label for="sound-engineer-search-speciality">{{ t('search.sound_engineer.speciality') }}</label>
        </FloatLabel>

        <div class="col-span-2 sm:col-span-1">
          <button
            type="button"
            class="p-select w-full lg:w-48 h-full min-h-[3.25rem] flex items-center justify-between gap-2 px-3 text-left"
            :aria-expanded="isCityPanelOpen"
            aria-haspopup="dialog"
            @click="(event) => cityPanel.toggle(event)"
          >
            <span class="flex flex-col min-w-0">
              <span class="text-xs text-surface-500 dark:text-surface-400">{{ t('search.sound_engineer.where') }}</span>
              <span class="truncate text-surface-900 dark:text-surface-0">{{ whereLabel }}</span>
            </span>
            <i class="pi pi-chevron-down text-surface-500 text-sm" aria-hidden="true" />
          </button>
          <Popover ref="cityPanel" @show="isCityPanelOpen = true" @hide="handleCityPanelHide">
            <div class="flex flex-col gap-3 w-72">
              <label for="sound-engineer-search-city" class="text-sm font-medium">{{ t('search.sound_engineer.where') }}</label>
              <CityAutoComplete
                ref="cityField"
                v-model="cityDraft"
                input-id="sound-engineer-search-city"
                :placeholder="t('search.sound_engineer.city_placeholder')"
                @blur="cityField?.pickFirstSuggestion()"
              />
              <span id="sound-engineer-search-radius" class="text-sm font-medium">{{ t('search.sound_engineer.radius') }}</span>
              <SelectButton
                v-model="filters.radius"
                :options="radiusOptions"
                option-label="label"
                option-value="value"
                :allow-empty="false"
                aria-labelledby="sound-engineer-search-radius"
                class="w-full [&_.p-togglebutton]:flex-1"
              />
              <Button
                v-if="filters.city"
                :label="t('search.sound_engineer.clear_city')"
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

        <FloatLabel variant="in" class="col-span-2 sm:col-span-1">
          <Select
            v-model="filters.style"
            input-id="sound-engineer-search-style"
            :options="styleStore.styles || []"
            option-label="name"
            data-key="id"
            filter
            show-clear
            class="w-full lg:w-44"
          />
          <label for="sound-engineer-search-style">{{ t('search.sound_engineer.style') }}</label>
        </FloatLabel>
      </div>

      <!-- Widens a search around a city; without one every sound engineer is already found. -->
      <label class="inline-flex items-center gap-2 text-sm cursor-pointer lg:ml-2">
        <ToggleSwitch v-model="filters.online" />
        {{ t('search.sound_engineer.online') }}
      </label>
    </section>

    <h2 class="font-semibold text-surface-900 dark:text-surface-0" aria-live="polite">
      {{ t('search.sound_engineer.count', { count: store.totalItems }, store.totalItems) }}
    </h2>

    <Message v-if="hasError" severity="error" :closable="false">{{ t('search.sound_engineer.error') }}</Message>

    <div v-if="isLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Skeleton v-for="n in 4" :key="n" height="11rem" border-radius="1rem" />
    </div>

    <template v-else-if="store.engineers.length">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 transition-opacity" :class="{ 'opacity-60': isRefreshing }" :aria-busy="isRefreshing">
        <SoundEngineerSearchCard v-for="engineer in store.engineers" :key="engineer.username" :engineer="engineer" />
      </div>
      <div v-if="store.hasMore" class="flex justify-center">
        <Button :label="t('search.sound_engineer.load_more')" :loading="isLoadingMore" severity="secondary" outlined @click="loadMore" />
      </div>
    </template>

    <div
      v-else-if="!hasError"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-8 text-center flex flex-col items-center gap-3"
    >
      <i class="pi pi-search text-3xl text-surface-400" aria-hidden="true" />
      <p class="font-semibold text-surface-900 dark:text-surface-0">{{ t('search.sound_engineer.empty_title') }}</p>
      <p class="text-surface-600 dark:text-surface-400">{{ t('search.sound_engineer.empty_text') }}</p>
      <Button
        v-if="hasFilters"
        :label="t('search.sound_engineer.clear_filters')"
        severity="secondary"
        outlined
        size="small"
        @click="clearFilters"
      />
    </div>
  </div>
</template>

<script setup>
defineOptions({ name: 'SoundEngineerSearch' })

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
import { SPECIALITY_KEYS } from '../../constants/soundEngineering.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { useSoundEngineerSearchStore } from '../../store/search/soundEngineer.js'
import { createSearchAutoRun } from '../../utils/searchAutoRun.js'
import { SPECIALITIES } from '../../utils/soundEngineerPage.js'
import {
  emptySoundEngineerFilters,
  SOUND_ENGINEER_RADII,
  soundEngineerFiltersFromUrlQuery,
  soundEngineerSearchParams,
  soundEngineerSearchUrlQuery
} from '../../utils/soundEngineerSearch.js'
import SoundEngineerSearchCard from './SoundEngineerSearchCard.vue'

/** The sound engineer search (#1410), on the teacher search's model. */
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useSoundEngineerSearchStore()
const styleStore = useStyleStore()

const filters = reactive(emptySoundEngineerFilters())
// The city field's own value: the typed text until a city is picked.
const cityDraft = ref(null)
const cityField = ref(null)
const cityPanel = ref(null)
const isCityPanelOpen = ref(false)

const isLoading = ref(true)
const isRefreshing = ref(false)
const isLoadingMore = ref(false)
const hasError = ref(false)

const specialityOptions = computed(() =>
  SPECIALITIES.map((speciality) => ({ value: speciality, label: t(SPECIALITY_KEYS[speciality]) }))
)
const radiusOptions = computed(() =>
  SOUND_ENGINEER_RADII.map((radius) => ({
    value: radius,
    label: t('search.sound_engineer.radius_option', { radius })
  }))
)
const whereLabel = computed(() =>
  filters.city
    ? t('search.sound_engineer.where_value', { city: filters.city.name, radius: filters.radius })
    : t('search.sound_engineer.anywhere')
)

// Not put back on unmount: the cached page unmounts after the next page has set its own title.
const documentTitle = useTitle(() => t('search.sound_engineer.title'), { restoreOnUnmount: false })

const autoRun = createSearchAutoRun({ refresh: refreshResults, record: () => {} })
const filtersKey = computed(() => JSON.stringify(soundEngineerSearchParams(filters)))
const hasFilters = computed(() => filtersKey.value !== '{}')
// What the list on screen was searched with: filling the filters from the address searches right after.
let searchedKey = null
// Only the latest refresh lifts the dimming, as only its answer shows.
let refreshToken = 0
// Kept alive while a visitor opens a page: a hidden search must not search, nor write onto another address.
const isActive = ref(true)

onActivated(() => {
  isActive.value = true
  documentTitle.value = t('search.sound_engineer.title')
})
onDeactivated(() => {
  isActive.value = false
  autoRun.cancel()
})

onMounted(async () => {
  const stylesLoaded = (
    styleStore.styles?.length ? Promise.resolve() : styleStore.loadStyles()
  ).catch(() => {
    // Without the list the style filter is empty; the search itself still works.
  })
  // Only a style in the address needs the list before searching: its slug becomes an id.
  if (route.query.style) await stylesLoaded
  Object.assign(filters, soundEngineerFiltersFromUrlQuery(route.query, styleStore.styles || []))
  cityDraft.value = filters.city
  searchedKey = filtersKey.value
  try {
    await store.search(soundEngineerSearchParams(filters))
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
    applied = await store.search(soundEngineerSearchParams(filters))
  } catch (error) {
    if (token === refreshToken) hasError.value = true
    throw error
  } finally {
    if (token === refreshToken) isRefreshing.value = false
  }
  if (!applied || !isActive.value) return
  router.replace({ query: soundEngineerSearchUrlQuery(filters) })
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
    await store.search(soundEngineerSearchParams(filters, store.currentPage + 1))
  } catch {
    hasError.value = true
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  Object.assign(filters, emptySoundEngineerFilters())
  cityDraft.value = null
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
</script>
