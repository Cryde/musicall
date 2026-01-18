<template>
    <div class="flex justify-end">
        <breadcrumb :items="[{'label': breadcrumbLabel}]"/>
    </div>

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
            {{ h1Title }}
        </h1>

        <Button
            label="Poster une annonce"
            icon="pi pi-megaphone"
            severity="info"
            size="small"
            class="w-full sm:w-auto"
            @click="handleOpenAnnounceModal"
        />
    </div>

    <div class="flex flex-wrap w-full gap-4 items-center">
        <Message severity="error" v-if="quickSearchErrors.length">
            <span v-for="error in quickSearchErrors">{{error}}</span>
        </Message>
        <div class="flex flex-wrap w-full gap-4 items-center">
            <InputText
                v-model="quickSearch"
                fluid
                size="large"
                class="flex-auto lg:flex-1 lg:mt-0 w-full lg:w-72 mr-0 lg:mr-6"
                placeholder="Taper votre recherche ici, exemple: Je recherche un guitariste qui joue du rock"
            />
            <Button
                label="Recherche rapide"
                @click="generateQuickSearchFilters"
                :loading="isFilterGenerating"
                icon="pi pi-search"
                severity="info"
                class="text-surface-500 dark:text-surface-400 shrink-0"
                :disabled="isSearching || isFilterGenerating || !isQuickSearchParamEnough"
            />
        </div>

        <Message size="small" severity="secondary" variant="simple">
            exemples :
            <span class="italic font-bold cursor-pointer hover:text-sky-300" @click="insertExample">je cherche un groupe de pop et rock qui a besoin d'un batteur</span>,
            <span class="italic font-bold cursor-pointer hover:text-sky-300" @click="insertExample">je recherche un guitariste pour mon groupe de funk</span>,
            <span class="italic font-bold cursor-pointer hover:text-sky-300" @click="insertExample">je recherche un bassiste sur Lyon</span>
        </Message>
        <Message v-if="hasAutoFilledFields" size="small" severity="warn" variant="simple" class="w-full">
            <i class="pi pi-exclamation-triangle mr-2" />
            La recherche rapide utilise l'IA pour interpréter votre demande. Veuillez vérifier les filtres générés et les ajuster si nécessaire.
        </Message>
    </div>

    <Divider class="w-full my-0!"/>

    <!-- Type selector with clear descriptions -->
    <div>
        <p class="text-sm text-surface-600 dark:text-surface-400 mb-3">Je recherche :</p>
        <div :class="['grid grid-cols-2 gap-3 max-w-lg transition-all duration-300 rounded-lg', autoFilledFields.type ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface-0 dark:ring-offset-surface-900' : '']">
            <div
                @click="selectType(selectSearchTypeOption[0])"
                :class="[
                    'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all',
                    selectSearchType?.key === 2
                        ? 'border-primary bg-primary/10 dark:bg-primary/20'
                        : 'border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-transparent hover:border-surface-400 dark:hover:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-800'
                ]"
            >
                <div :class="['flex items-center justify-center w-10 h-10 rounded-full', selectSearchType?.key === 2 ? 'bg-primary text-white' : 'bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-300']">
                    <i class="pi pi-user text-lg" />
                </div>
                <div>
                    <div class="font-medium text-surface-900 dark:text-surface-0">Un musicien</div>
                    <div class="text-xs text-surface-500 dark:text-surface-400">Pour mon groupe</div>
                </div>
            </div>
            <div
                @click="selectType(selectSearchTypeOption[1])"
                :class="[
                    'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all',
                    selectSearchType?.key === 1
                        ? 'border-primary bg-primary/10 dark:bg-primary/20'
                        : 'border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-transparent hover:border-surface-400 dark:hover:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-800'
                ]"
            >
                <div :class="['flex items-center justify-center w-10 h-10 rounded-full', selectSearchType?.key === 1 ? 'bg-primary text-white' : 'bg-surface-200 dark:bg-surface-800 text-surface-600 dark:text-surface-300']">
                    <i class="pi pi-users text-lg" />
                </div>
                <div>
                    <div class="font-medium text-surface-900 dark:text-surface-0">Un groupe</div>
                    <div class="text-xs text-surface-500 dark:text-surface-400">Pour rejoindre</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile: Filter toggle + Search button always visible -->
    <div class="lg:hidden flex gap-2 mb-3">
        <Button
            :label="showMobileFilters ? 'Masquer les filtres' : 'Afficher les filtres'"
            :icon="showMobileFilters ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
            icon-pos="right"
            severity="secondary"
            outlined
            size="small"
            class="flex-1"
            @click="showMobileFilters = !showMobileFilters"
        />
        <Button
            severity="info"
            icon="pi pi-search"
            :disabled="isSearching || isFilterGenerating"
            label="Rechercher"
            size="small"
            @click="search"
        />
    </div>

    <!-- Filters section -->
    <div :class="[{ 'hidden': !showMobileFilters && !isLargeScreen }]">
        <div class="flex flex-col lg:flex-row flex-wrap gap-3 items-stretch lg:items-center">
            <div :class="['transition-all duration-300 rounded-lg w-full lg:w-auto', autoFilledFields.instrument ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface-0 dark:ring-offset-surface-900' : '']">
                <Select
                    v-model="selectedInstrument"
                    :options="instrumentStore.instruments"
                    filter
                    optionLabel="musician_name"
                    placeholder="Sélectionnez un instrument"
                    class="w-full lg:w-56"
                />
            </div>
            <div :class="['transition-all duration-300 rounded-lg w-full lg:w-auto', autoFilledFields.styles ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface-0 dark:ring-offset-surface-900' : '']">
                <MultiSelect
                    v-model="selectedStyles"
                    :options="styleStore.styles"
                    placeholder="Styles musicaux"
                    option-label="name"
                    filter
                    showClear
                    class="w-full lg:w-56 text-surface-900 dark:text-surface-0"
                />
            </div>
            <div :class="['transition-all duration-300 rounded-lg w-full lg:w-auto', autoFilledFields.location ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface-0 dark:ring-offset-surface-900' : '']">
                <AutoComplete
                    v-model="selectedLocation"
                    :suggestions="locationSuggestions"
                    optionLabel="name"
                    placeholder="Ville (optionnel)"
                    fluid
                    @complete="searchLocation"
                >
                    <template #option="{ option }">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-map-marker text-primary" />
                            <div>
                                <div class="font-medium">{{ option.name }}</div>
                                <div v-if="option.context" class="text-sm text-surface-500">{{ option.context }}</div>
                            </div>
                        </div>
                    </template>
                </AutoComplete>
            </div>
            <!-- Desktop: Search buttons inside filters row -->
            <div class="hidden lg:block">
                <Button
                    severity="info"
                    icon="pi pi-search"
                    :disabled="isSearching || isFilterGenerating"
                    label="Rechercher"
                    @click="search"
                />
                <Button
                    text
                    icon="pi pi-times"
                    severity="secondary"
                    v-tooltip.bottom="'Effacer les filtres'"
                    @click="clearAllFilters"
                />
            </div>
            <!-- Mobile: Clear button only (search button is above) -->
            <div class="lg:hidden">
                <Button
                    text
                    icon="pi pi-times"
                    severity="secondary"
                    label="Effacer les filtres"
                    @click="clearAllFilters"
                />
            </div>
        </div>
    </div>

    <!-- Active filters summary -->
    <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2 mt-4">
        <span class="text-sm text-surface-500 dark:text-surface-400">Filtres actifs :</span>
        <Chip
            v-if="selectSearchType"
            :label="selectSearchType.key === 2 ? 'Musicien' : 'Groupe'"
            removable
            @remove="selectSearchType = null"
            class="text-sm"
        />
        <Chip
            v-if="selectedInstrument"
            :label="selectedInstrument.musician_name"
            removable
            @remove="selectedInstrument = null"
            class="text-sm"
        />
        <Chip
            v-for="style in selectedStyles"
            :key="style.id"
            :label="style.name"
            removable
            @remove="removeStyle(style)"
            class="text-sm"
        />
        <Chip
            v-if="selectedLocation && typeof selectedLocation === 'object'"
            :label="selectedLocation.name"
            icon="pi pi-map-marker"
            removable
            @remove="selectedLocation = null"
            class="text-sm"
        />
    </div>


    <!-- LLM processing state (quick search) -->
    <div v-if="isFilterGenerating" class="mt-8">
        <div class="flex flex-col items-center justify-center py-16 px-4 bg-surface-50 dark:bg-surface-800 rounded-2xl">
            <div class="w-20 h-20 rounded-full bg-primary/10 flex items-center justify-center mb-6">
                <i class="pi pi-spin pi-sparkles text-4xl text-primary" />
            </div>
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
                Analyse de votre recherche...
            </h2>
            <p class="text-surface-600 dark:text-surface-300 text-center max-w-md mb-4">
                Notre IA analyse votre demande pour trouver les meilleurs filtres correspondants.
            </p>
            <div class="flex items-center gap-3 text-sm text-surface-500 dark:text-surface-400">
                <div class="flex items-center gap-2">
                    <i class="pi pi-check-circle text-green-500" />
                    <span>Lecture de la demande</span>
                </div>
                <i class="pi pi-arrow-right" />
                <div class="flex items-center gap-2">
                    <i class="pi pi-spin pi-spinner text-primary" />
                    <span>Identification des critères</span>
                </div>
                <i class="pi pi-arrow-right hidden sm:inline" />
                <div class="hidden sm:flex items-center gap-2 text-surface-400 dark:text-surface-500">
                    <i class="pi pi-circle" />
                    <span>Recherche</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading state (direct search) -->
    <div v-else-if="isSearching" class="mt-6">
        <p class="text-surface-600 dark:text-surface-300 mb-4">
            <i class="pi pi-spin pi-spinner mr-2" />
            Recherche en cours...
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div v-for="i in 8" :key="i" class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4">
                <Skeleton height="8rem" class="mb-4" />
                <Skeleton width="70%" height="1.5rem" class="mb-2" />
                <Skeleton width="50%" height="1rem" class="mb-2" />
                <Skeleton width="40%" height="1rem" />
            </div>
        </div>
    </div>

    <!-- No results state -->
    <div v-else-if="musicianSearchStore.announces.length === 0" class="mt-8">
        <div class="flex flex-col items-center justify-center py-12 px-4 bg-surface-50 dark:bg-surface-800 rounded-2xl">
            <div class="w-20 h-20 rounded-full bg-surface-200 dark:bg-surface-700 flex items-center justify-center mb-6">
                <i class="pi pi-search text-4xl text-surface-500 dark:text-surface-400" />
            </div>
            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
                Aucun résultat trouvé
            </h2>
            <p class="text-surface-600 dark:text-surface-300 text-center max-w-md mb-6">
                Aucune annonce ne correspond à vos critères de recherche.
                Pourquoi ne pas créer la vôtre ?
            </p>
            <Button
                label="Créer une annonce avec ces critères"
                icon="pi pi-megaphone"
                severity="success"
                size="large"
                @click="handleOpenAnnounceModalFromSearch"
            />
            <p class="text-sm text-surface-500 dark:text-surface-400 mt-4">
                Votre annonce sera visible par tous les musiciens de la communauté
            </p>
        </div>
    </div>

    <!-- Results state -->
    <template v-else>
        <div v-if="hasActiveFilters" class="flex flex-wrap items-center justify-end gap-4 mt-6">
            <Button
                label="Créer une annonce depuis cette recherche"
                icon="pi pi-plus"
                severity="success"
                size="small"
                @click="handleOpenAnnounceModalFromSearch"
            />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mt-4">
            <MusicianAnnounceBlockItem
                v-for="announce in musicianSearchStore.announces"
                :key="announce.id"
                :type="announce.type"
                :user="announce.user"
                :styles="announce.styles"
                :location_name="announce.location_name"
                :distance="announce.distance"
                :instrument="announce.instrument.name"
            />
        </div>

        <!-- Load more button -->
        <div v-if="!userSecurityStore.isAuthenticated || musicianSearchStore.lastBatchSize >= 12" class="flex justify-center mt-8 mb-9">
            <Button
                :label="userSecurityStore.isAuthenticated ? 'Voir plus de résultats' : 'Voir plus'"
                :icon="isLoadingMore ? 'pi pi-spin pi-spinner' : 'pi pi-arrow-down'"
                severity="secondary"
                size="large"
                :loading="isLoadingMore"
                @click="loadMore"
            />
        </div>
        <div v-else class="mb-9"></div>
    </template>

    <AddAnnounceModal
        v-model:visible="showAnnounceModal"
        :initial-type="announceInitialType"
        :initial-instrument="announceInitialInstrument"
        :initial-styles="announceInitialStyles"
        :initial-location="announceInitialLocation"
        @created="handleAnnounceCreated"
    />
    <AuthRequiredModal
        v-model:visible="showAuthModal"
        :message="authModalMessage"
    />
</template>
<script setup>
import { useDebounceFn, useMediaQuery, useTitle } from '@vueuse/core'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Chip from 'primevue/chip'
import Divider from 'primevue/divider'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import geocodingApi from '../../api/geocoding.js'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import { useInstrumentStore } from '../../store/attribute/instrument.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { useMusicianSearchStore } from '../../store/search/musician.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import AddAnnounceModal from '../User/Announce/AddAnnounceModal.vue'
import MusicianAnnounceBlockItem from './MusicianAnnounceBlockItem.vue'

const route = useRoute()

const styleStore = useStyleStore()
const instrumentStore = useInstrumentStore()
const musicianSearchStore = useMusicianSearchStore()
const userSecurityStore = useUserSecurityStore()

// Instrument name mapping for page titles
const instrumentTitles = {
  guitare: 'guitariste',
  batterie: 'batteur',
  basse: 'bassiste',
  chant: 'chanteur',
  piano: 'pianiste'
}

const prefilledInstrumentSlug = computed(() => route.meta.instrumentSlug || null)

const pageTitle = computed(() => {
  if (prefilledInstrumentSlug.value && instrumentTitles[prefilledInstrumentSlug.value]) {
    return `Rechercher un ${instrumentTitles[prefilledInstrumentSlug.value]} - MusicAll`
  }
  return 'Rechercher un musicien ou un groupe - MusicAll'
})

const h1Title = computed(() => {
  if (prefilledInstrumentSlug.value && instrumentTitles[prefilledInstrumentSlug.value]) {
    return `Rechercher un ${instrumentTitles[prefilledInstrumentSlug.value]}`
  }
  return 'Rechercher un musicien'
})

const breadcrumbLabel = computed(() => {
  if (prefilledInstrumentSlug.value && instrumentTitles[prefilledInstrumentSlug.value]) {
    return `Rechercher un ${instrumentTitles[prefilledInstrumentSlug.value]}`
  }
  return 'Rechercher un musicien'
})

useTitle(pageTitle)

onMounted(async () => {
  await instrumentStore.loadInstruments()
  await styleStore.loadStyles()

  // Pre-fill instrument if specified in route meta
  applyPrefilledInstrument()

  // Load initial results without filters
  await loadInitialResults()
})

async function loadInitialResults() {
  isSearching.value = true
  isSearchMade.value = true
  await musicianSearchStore.searchAnnounces({})
  isSearching.value = false
}

function applyPrefilledInstrument() {
  if (prefilledInstrumentSlug.value) {
    const instrument = instrumentStore.instruments.find(
      (i) => i.slug === prefilledInstrumentSlug.value
    )
    if (instrument) {
      selectedInstrument.value = instrument
    }
  } else {
    selectedInstrument.value = null
  }
}

// Watch for route changes to update pre-filled instrument
watch(prefilledInstrumentSlug, () => {
  applyPrefilledInstrument()
})

const quickSearch = ref('')
const quickSearchErrors = ref([])
const isSearching = ref(false)
const isFilterGenerating = ref(false)
const isSearchMade = ref(false)
const selectedInstrument = ref(null)
const selectedStyles = ref([])
const selectedLocation = ref(null)
const locationSuggestions = ref([])
const selectSearchType = ref(null)
const selectSearchTypeOption = [
  { key: 2, name: 'Musiciens' },
  { key: 1, name: 'Groupe' }
]

const showAnnounceModal = ref(false)
const createFromSearch = ref(false)
const showAuthModal = ref(false)
const authModalMessage = ref('')

// Mobile responsive
const showMobileFilters = ref(false)
const isLargeScreen = useMediaQuery('(min-width: 1024px)')

// Track which fields were auto-filled from quick search
const autoFilledFields = ref({
  type: false,
  instrument: false,
  styles: false,
  location: false
})
let autoFilledTimeout = null

function clearAutoFilledIndicators() {
  if (autoFilledTimeout) {
    clearTimeout(autoFilledTimeout)
  }
  autoFilledFields.value = {
    type: false,
    instrument: false,
    styles: false,
    location: false
  }
}

function scheduleAutoFilledClear() {
  if (autoFilledTimeout) {
    clearTimeout(autoFilledTimeout)
  }
  autoFilledTimeout = setTimeout(() => {
    clearAutoFilledIndicators()
  }, 3000)
}

const debouncedLocationSearch = useDebounceFn(async (query) => {
  try {
    locationSuggestions.value = await geocodingApi.searchCities(query)
  } catch (error) {
    console.error('Error searching location:', error)
    locationSuggestions.value = []
  }
}, 300)

function searchLocation(event) {
  if (event.query.length >= 2) {
    debouncedLocationSearch(event.query)
  }
}

const isQuickSearchParamEnough = computed(() => {
  return quickSearch.value !== '' && quickSearch.value.length > 4
})

const hasAutoFilledFields = computed(() => {
  const fields = autoFilledFields.value
  return fields.type || fields.instrument || fields.styles || fields.location
})

const hasActiveFilters = computed(() => {
  return (
    selectSearchType.value !== null ||
    selectedInstrument.value !== null ||
    selectedStyles.value.length > 0 ||
    (selectedLocation.value && typeof selectedLocation.value === 'object')
  )
})

function removeStyle(style) {
  selectedStyles.value = selectedStyles.value.filter((s) => s.id !== style.id)
}

function selectType(type) {
  selectSearchType.value = type
  trackUmamiEvent('musician-type-toggle', { type: type.name })
}

// Computed values for announce modal initial values (only when creating from search)
const announceInitialType = computed(() => {
  if (!createFromSearch.value) return null
  // key 2 = Musicien (searching for a musician) => announce type "musician" (looking for a musician)
  // key 1 = Groupe (searching for a band) => announce type "band" (looking for a band)
  if (!selectSearchType.value) return null
  return selectSearchType.value.key === 2 ? 'musician' : 'band'
})

const announceInitialInstrument = computed(() => {
  if (!createFromSearch.value) return null
  return selectedInstrument.value
})

const announceInitialStyles = computed(() => {
  if (!createFromSearch.value) return []
  return selectedStyles.value
})

const announceInitialLocation = computed(() => {
  if (!createFromSearch.value) return null
  if (!selectedLocation.value || typeof selectedLocation.value !== 'object') return null
  return selectedLocation.value
})

function insertExample(e) {
  quickSearch.value = e.target.textContent
}

function buildSearchParams() {
  const params = {}
  if (selectSearchType.value) {
    params.type = selectSearchType.value.key
  }
  if (selectedInstrument.value) {
    params.instrument = selectedInstrument.value.id
  }
  if (selectedStyles.value?.length > 0) {
    params.styles = selectedStyles.value.map((style) => style.id)
  }
  if (selectedLocation.value && typeof selectedLocation.value === 'object') {
    params.latitude = selectedLocation.value.latitude
    params.longitude = selectedLocation.value.longitude
  }
  return params
}

async function search() {
  quickSearchErrors.value = []
  isSearching.value = true
  const searchFilters = {
    type: selectSearchType.value?.name || null,
    instrument: selectedInstrument.value?.musician_name || null,
    styles: selectedStyles.value.map(s => s.name).join(', ') || null,
    location: selectedLocation.value?.name || null
  }
  trackUmamiEvent('musician-search-submit', searchFilters)
  const params = buildSearchParams()
  await musicianSearchStore.searchAnnounces(params)
  isSearching.value = false
  isSearchMade.value = true

  if (musicianSearchStore.announces.length === 0) {
    trackUmamiEvent('musician-search-no-results', searchFilters)
  }
}

const isLoadingMore = ref(false)

async function loadMore() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Connectez-vous pour voir plus de résultats et contacter les musiciens.'
    showAuthModal.value = true
    return
  }

  isLoadingMore.value = true
  const params = buildSearchParams()
  params.page = musicianSearchStore.currentPage + 1
  params.append = true
  await musicianSearchStore.searchAnnounces(params)
  isLoadingMore.value = false
}

async function generateQuickSearchFilters() {
  const searchTxt = quickSearch.value
  trackUmamiEvent('musician-quick-search', { query: searchTxt })
  clearAllFilters(true)
  quickSearch.value = searchTxt
  quickSearchErrors.value = []
  isFilterGenerating.value = true
  clearAutoFilledIndicators()
  try {
    await musicianSearchStore.getSearchAnnouncesFilters({ search: searchTxt })

    // Track which fields are being auto-filled
    const filledFields = { type: false, instrument: false, styles: false, location: false }

    selectSearchType.value = selectSearchTypeOption.find(
      (type) => type.key === musicianSearchStore.filters.type
    )
    if (selectSearchType.value) {
      filledFields.type = true
    }

    selectedInstrument.value = instrumentStore.instruments.find(
      (i) => i.id === musicianSearchStore.filters.instrument
    )
    if (selectedInstrument.value) {
      filledFields.instrument = true
    }

    if (musicianSearchStore.filters.styles.length) {
      selectedStyles.value = styleStore.styles.filter((style) =>
        musicianSearchStore.filters.styles.includes(style.id)
      )
      if (selectedStyles.value.length) {
        filledFields.styles = true
      }
    }

    // Reverse geocode if location coordinates are provided
    if (musicianSearchStore.filters.latitude && musicianSearchStore.filters.longitude) {
      try {
        const location = await geocodingApi.reverseGeocode(
          musicianSearchStore.filters.latitude,
          musicianSearchStore.filters.longitude
        )
        if (location) {
          selectedLocation.value = location
          filledFields.location = true
        }
      } catch (e) {
        console.error('Error reverse geocoding:', e)
      }
    }

    // Show auto-filled indicators and schedule their removal
    autoFilledFields.value = filledFields
    scheduleAutoFilledClear()

    await search()
  } catch (e) {
    if (e?.response?.status === 422) {
      quickSearchErrors.value = e.response.data.violations.map((violation) => violation.message)
    } else {
      quickSearchErrors.value = [
        'Nous ne pouvons pas répondre à cette demande. Reformulez votre recherche.'
      ]
    }
  }
  isFilterGenerating.value = false
}

function clearAllFilters(skipTracking = false) {
  if (!skipTracking) {
    trackUmamiEvent('musician-filter-clear')
  }
  quickSearchErrors.value = []
  selectedInstrument.value = null
  selectedStyles.value = []
  selectedLocation.value = null
  locationSuggestions.value = []
  quickSearch.value = ''
  selectSearchType.value = null
  // Reset results to initial state
  musicianSearchStore.clear()
  isSearchMade.value = false
}

function handleOpenAnnounceModal() {
  trackUmamiEvent('musician-post-ad-click')
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Si vous souhaitez poster une annonce, vous devez vous connecter.'
    showAuthModal.value = true
    return
  }
  createFromSearch.value = false
  showAnnounceModal.value = true
}

function handleOpenAnnounceModalFromSearch() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Si vous souhaitez poster une annonce, vous devez vous connecter.'
    showAuthModal.value = true
    return
  }
  createFromSearch.value = true
  showAnnounceModal.value = true
}

async function handleAnnounceCreated() {
  createFromSearch.value = false
  // Refresh search results
  await search()
}

onUnmounted(() => {
  musicianSearchStore.clear()
  instrumentStore.clear()
  styleStore.clear()
  isSearching.value = false
  isSearchMade.value = false
  quickSearch.value = ''
  isSearching.value = false
  selectedInstrument.value = null
  selectedStyles.value = []
  selectedLocation.value = null
  locationSuggestions.value = []
  quickSearchErrors.value = []
  selectSearchType.value = { key: 2, name: 'Musiciens' }
})
</script>
