<template>
  <div class="flex flex-col gap-5 max-w-6xl w-full mx-auto">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-0">
          {{ t(config.text.heading) }}
        </h1>
        <p class="mt-1 text-surface-600 dark:text-surface-400">{{ t(config.text.intro) }}</p>
      </div>
      <p class="text-sm text-surface-600 dark:text-surface-400">
        {{ t(config.text.cta) }}
        <router-link :to="{ name: 'app_user_services' }" class="text-primary-700 dark:text-primary-300 font-medium hover:underline">
          {{ t('search.service.cta_link') }}
        </router-link>
      </p>
    </div>

    <!-- The filters search on their own as they change. -->
    <section
      :aria-label="t('search.service.filters_label')"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 sm:p-5 flex flex-wrap items-center gap-3"
    >
      <div class="grid grid-cols-2 lg:flex lg:flex-wrap gap-2.5 w-full lg:w-auto">
        <FloatLabel variant="in" class="col-span-2 sm:col-span-1">
          <Select
            v-model="filters.speciality"
            :input-id="`${idPrefix}-speciality`"
            :options="specialityOptions"
            option-label="label"
            option-value="value"
            show-clear
            class="w-full lg:w-52"
          />
          <label :for="`${idPrefix}-speciality`">{{ t('search.service.speciality') }}</label>
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
              <span class="text-xs text-surface-500 dark:text-surface-400">{{ t('search.service.where') }}</span>
              <span class="truncate text-surface-900 dark:text-surface-0">{{ whereLabel }}</span>
            </span>
            <i class="pi pi-chevron-down text-surface-500 text-sm" aria-hidden="true" />
          </button>
          <Popover ref="cityPanel" @show="isCityPanelOpen = true" @hide="handleCityPanelHide">
            <div class="flex flex-col gap-3 w-72">
              <label :for="`${idPrefix}-city`" class="text-sm font-medium">{{ t('search.service.where') }}</label>
              <CityAutoComplete
                ref="cityField"
                v-model="cityDraft"
                :input-id="`${idPrefix}-city`"
                :placeholder="t('search.service.city_placeholder')"
                @blur="cityField?.pickFirstSuggestion()"
              />
              <span :id="`${idPrefix}-radius`" class="text-sm font-medium">{{ t('search.service.radius') }}</span>
              <SelectButton
                v-model="filters.radius"
                :options="radiusOptions"
                option-label="label"
                option-value="value"
                :allow-empty="false"
                :aria-labelledby="`${idPrefix}-radius`"
                class="w-full [&_.p-togglebutton]:flex-1"
              />
              <Button
                v-if="filters.city"
                :label="t('search.service.clear_city')"
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
            :input-id="`${idPrefix}-style`"
            :options="styleStore.styles || []"
            option-label="name"
            data-key="id"
            filter
            show-clear
            class="w-full lg:w-44"
          />
          <label :for="`${idPrefix}-style`">{{ t('search.service.style') }}</label>
        </FloatLabel>
      </div>

      <!-- Widens a search around a city; without one every member is already found. -->
      <label v-if="config.hasOnline" class="inline-flex items-center gap-2 text-sm cursor-pointer lg:ml-2">
        <ToggleSwitch v-model="filters.online" />
        {{ t(config.text.online) }}
      </label>
    </section>

    <h2 class="font-semibold text-surface-900 dark:text-surface-0" aria-live="polite">
      {{ t(config.text.count, { count: totalItems }, totalItems) }}
    </h2>

    <Message v-if="hasError" severity="error" :closable="false">{{ t('search.service.error') }}</Message>

    <div v-if="isLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Skeleton v-for="n in 4" :key="n" height="11rem" border-radius="1rem" />
    </div>

    <template v-else-if="results.length">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 transition-opacity" :class="{ 'opacity-60': isRefreshing }" :aria-busy="isRefreshing">
        <SpecialitySearchCard
          v-for="result in results"
          :key="result.username"
          :result="result"
          :page-route="SERVICE_KINDS[kind].pageRoute"
          :speciality-keys="config.specialityKeys"
          :unit-keys="config.unitKeys"
          :days="config.daysField ? result[config.daysField] : null"
        />
      </div>
      <div v-if="hasMore" class="flex justify-center">
        <Button :label="t(config.text.loadMore)" :loading="isLoadingMore" severity="secondary" outlined @click="loadMore" />
      </div>
    </template>

    <div
      v-else-if="!hasError"
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-8 text-center flex flex-col items-center gap-3"
    >
      <i class="pi pi-search text-3xl text-surface-400" aria-hidden="true" />
      <p class="font-semibold text-surface-900 dark:text-surface-0">{{ t(config.text.emptyTitle) }}</p>
      <p class="text-surface-600 dark:text-surface-400">{{ t(config.text.emptyText) }}</p>
      <Button
        v-if="hasFilters"
        :label="t('search.service.clear_filters')"
        severity="secondary"
        outlined
        size="small"
        @click="clearFilters"
      />
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import FloatLabel from 'primevue/floatlabel'
import Message from 'primevue/message'
import Popover from 'primevue/popover'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Skeleton from 'primevue/skeleton'
import ToggleSwitch from 'primevue/toggleswitch'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import serviceSearchApi from '../../api/service/serviceSearch.js'
import CityAutoComplete from '../../components/Global/CityAutoComplete.vue'
import { SERVICE_KINDS } from '../../constants/serviceKinds.js'
import { SPECIALITY_SEARCHES } from '../../constants/specialitySearches.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { createSearchAutoRun } from '../../utils/searchAutoRun.js'
import {
  emptySpecialityFilters,
  SPECIALITY_SEARCH_RADII,
  specialityFiltersFromUrlQuery,
  specialitySearchParams,
  specialitySearchUrlQuery
} from '../../utils/specialitySearch.js'
import SpecialitySearchCard from './SpecialitySearchCard.vue'

/** The searches by speciality (#1410, #1411): sound engineers, photographers and videographers, on the teacher search's model. */
const props = defineProps({
  /** A ServiceKind value of SPECIALITY_SEARCHES, from the route. */
  kind: { type: String, required: true }
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const styleStore = useStyleStore()

const config = computed(() => SPECIALITY_SEARCHES[props.kind])
const specialities = computed(() => Object.keys(config.value.specialityKeys))
const idPrefix = computed(() => `${props.kind}-search`)

const filters = reactive(emptySpecialityFilters())
// The city field's own value: the typed text until a city is picked.
const cityDraft = ref(null)
const cityField = ref(null)
const cityPanel = ref(null)
const isCityPanelOpen = ref(false)

const results = ref([])
const totalItems = ref(0)
const currentPage = ref(1)
const hasMore = computed(() => results.value.length < totalItems.value)
const isLoading = ref(true)
const isRefreshing = ref(false)
const isLoadingMore = ref(false)
const hasError = ref(false)

const specialityOptions = computed(() =>
  specialities.value.map((speciality) => ({
    value: speciality,
    label: t(config.value.specialityKeys[speciality])
  }))
)
const radiusOptions = computed(() =>
  SPECIALITY_SEARCH_RADII.map((radius) => ({
    value: radius,
    label: t('search.service.radius_option', { radius })
  }))
)
const whereLabel = computed(() =>
  filters.city
    ? t('search.service.where_value', { city: filters.city.name, radius: filters.radius })
    : t('search.service.anywhere')
)

useTitle(() => t(config.value.text.title), { restoreOnUnmount: false })

const autoRun = createSearchAutoRun({ refresh: refreshResults, record: () => {} })
const filtersKey = computed(() => JSON.stringify(specialitySearchParams(filters)))
const hasFilters = computed(() => filtersKey.value !== '{}')
// What the list on screen was searched with: filling the filters from the address searches right after.
let searchedKey = null
// Only the latest refresh lifts the dimming, as only its answer shows.
let refreshToken = 0
// The filters search as they change, so two answers can cross: only the latest one shows.
let latestRequest = 0
// A search answering after the visitor left must not write onto another address.
let isActive = true

/** `params` from specialitySearchParams(); a page past the first is added to the list. Whether its answer shows. */
async function search(params) {
  const request = ++latestRequest
  const data = await serviceSearchApi.search(config.value.apiRoute, params)
  if (request !== latestRequest) return false

  const page = params.page ?? 1
  results.value = page > 1 ? [...results.value, ...data.member] : data.member
  totalItems.value = data.totalItems
  currentPage.value = page
  return true
}

/** The filters the address holds, then the search: on the way in, and when the menu switches kind. */
async function start() {
  isLoading.value = true
  hasError.value = false
  autoRun.cancel()
  const stylesLoaded = (
    styleStore.styles?.length ? Promise.resolve() : styleStore.loadStyles()
  ).catch(() => {
    // Without the list the style filter is empty; the search itself still works.
  })
  // Only a style in the address needs the list before searching: its slug becomes an id.
  if (route.query.style) await stylesLoaded
  Object.assign(
    filters,
    specialityFiltersFromUrlQuery(route.query, styleStore.styles || [], specialities.value)
  )
  // Only a kind given remotely is searched that way.
  if (!config.value.hasOnline) filters.online = false
  cityDraft.value = filters.city
  searchedKey = filtersKey.value
  try {
    await search(specialitySearchParams(filters))
  } catch {
    hasError.value = true
  }
  isLoading.value = false
}

onMounted(start)
watch(() => props.kind, start)
onBeforeUnmount(() => {
  isActive = false
  autoRun.cancel()
})

async function refreshResults() {
  const token = ++refreshToken
  searchedKey = filtersKey.value
  isRefreshing.value = true
  hasError.value = false
  let applied = false
  try {
    applied = await search(specialitySearchParams(filters))
  } catch (error) {
    if (token === refreshToken) hasError.value = true
    throw error
  } finally {
    if (token === refreshToken) isRefreshing.value = false
  }
  if (!applied || !isActive) return
  router.replace({ query: specialitySearchUrlQuery(filters) })
}

watch(filtersKey, (key) => {
  if (isLoading.value || key === searchedKey) return
  autoRun.changed()
})

async function loadMore() {
  // The page after the one on screen, so a refresh still on its way lands first.
  await autoRun.settleNow()
  isLoadingMore.value = true
  try {
    await search(specialitySearchParams(filters, currentPage.value + 1))
  } catch {
    hasError.value = true
  } finally {
    isLoadingMore.value = false
  }
}

function clearFilters() {
  Object.assign(filters, emptySpecialityFilters())
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
