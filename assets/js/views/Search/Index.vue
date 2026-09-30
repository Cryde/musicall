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

    <!-- The guided mode (#1084): three questions instead of the filters, over the same results. -->
    <GuidedSearch
        v-if="isGuided && isGuidedReady"
        v-model:instrument="selectedInstrument"
        v-model:location="selectedLocation"
        v-model:radius="selectedRadius"
        v-model:selected-styles="selectedStyles"
        :instruments="instrumentStore.instruments"
        :styles="styleStore.styles"
        :looking-for-band="guidedLookingForBand"
        :initially-answered="guidedInitiallyAnswered"
        class="mb-2"
        @answer="handleGuidedAnswer"
        @toggle-type="handleGuidedToggleType"
    />

    <template v-else-if="!isGuided">
    <!-- Quick Search Section -->
    <div class="mb-6">
        <Message severity="error" v-if="quickSearchErrors.length" class="mb-4">
            <span v-for="error in quickSearchErrors" :key="error">{{ error }}</span>
        </Message>

        <!-- Search Bar -->
        <div class="relative w-full">
            <div class="absolute left-4 top-1/2 -translate-y-1/2 text-primary">
                <i class="pi pi-sparkles text-xl"></i>
            </div>
            <input
                v-model="quickSearch"
                type="text"
                aria-label="Décrivez votre recherche"
                placeholder="Décrivez ce que vous cherchez..."
                class="w-full pl-12 pr-16 sm:pr-36 py-3 sm:py-4 text-base sm:text-lg rounded-2xl border-2 border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 focus:border-primary focus:ring-4 focus:ring-primary/20 outline-none transition-all text-surface-900 dark:text-surface-0 placeholder:text-surface-400"
                @keyup.enter="isQuickSearchParamEnough && generateQuickSearchFilters()"
            >
            <button
                @click="generateQuickSearchFilters"
                :disabled="isSearching || isFilterGenerating || !isQuickSearchParamEnough"
                class="absolute right-2 top-1/2 -translate-y-1/2 px-3 sm:px-5 py-2 sm:py-2.5 bg-primary text-white rounded-xl flex items-center gap-2 hover:bg-primary-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
                <i :class="isFilterGenerating ? 'pi pi-spin pi-spinner' : 'pi pi-search'"></i>
                <span class="hidden sm:inline">Rechercher</span>
            </button>
        </div>

        <!-- Examples -->
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-3 text-sm text-surface-600 dark:text-surface-400">
            <span>Essayez :</span>
            <button class="text-primary hover:underline cursor-pointer" @click="insertExample">guitariste rock Paris</button>
            <span class="text-surface-300 dark:text-surface-600">•</span>
            <button class="text-primary hover:underline cursor-pointer" @click="insertExample">groupe de jazz cherchant bassiste</button>
            <span class="text-surface-300 dark:text-surface-600">•</span>
            <button class="text-primary hover:underline cursor-pointer" @click="insertExample">batteur metal Lyon</button>
        </div>

        <!-- AI Warning -->
        <Message v-if="hasAutoFilledFields" size="small" severity="warn" variant="simple" class="mt-4">
            <i class="pi pi-info-circle mr-2" />
            Les filtres ont été générés par IA. Vérifiez et ajustez si nécessaire.
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
            :disabled="isSearching || isFilterGenerating || isCityLoading"
            :loading="isCityLoading"
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
                <CityAutoComplete
                    ref="cityField"
                    v-model="selectedLocation"
                    v-model:loading="isCityLoading"
                    placeholder="Ville (optionnel)"
                />
            </div>
            <!-- Desktop: Search buttons inside filters row -->
            <div class="hidden lg:block">
                <Button
                    severity="info"
                    icon="pi pi-search"
                    :disabled="isSearching || isFilterGenerating || isCityLoading"
                    :loading="isCityLoading"
                    label="Rechercher"
                    @click="search"
                />
                <Button
                    text
                    icon="pi pi-times"
                    severity="secondary"
                    aria-label="Effacer les filtres"
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
    </template>

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
                <div class="hidden sm:flex items-center gap-2 text-surface-500 dark:text-surface-400">
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

    <!-- No results, guided: the wider searches that would find something -->
    <GuidedNoResults
        v-else-if="isGuided && musicianSearchStore.announces.length === 0"
        :sought="guidedSought"
        :where="selectedLocation?.name ? `autour de ${selectedLocation.name}` : ''"
        :widening="guidedWidening"
        @widen-radius="handleGuidedWidenRadius"
        @all-styles="handleGuidedAllStyles"
        @publish="handleOpenAnnounceModalFromSearch"
    />

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
        <!-- Guided: the list follows each answer, and says so. -->
        <p v-if="isGuided" class="mt-6 mb-0 flex items-center gap-2 text-surface-700 dark:text-surface-300">
            <span class="w-2 h-2 rounded-full bg-teal-500" aria-hidden="true" />
            Ces <strong class="text-surface-900 dark:text-surface-0">{{ guidedSought }}</strong> correspondent{{ guidedComplete ? '' : ' déjà' }}
        </p>
        <div v-else-if="hasActiveFilters" class="flex flex-wrap items-center justify-end gap-4 mt-6">
            <Button
                label="Créer une annonce depuis cette recherche"
                icon="pi pi-plus"
                severity="success"
                size="small"
                @click="handleOpenAnnounceModalFromSearch"
            />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mt-4 items-stretch">
            <MusicianAnnounceBlockItem
                v-for="announce in musicianSearchStore.announces"
                :key="announce.id"
                :type="announce.type"
                :user="announce.user"
                :styles="announce.styles"
                :location_name="announce.location_name"
                :distance="announce.distance"
                :instrument="announce.instrument.name"
                :highlighted-styles="isGuided ? selectedStyles.map((style) => style.name) : []"
                from="search"
            />
            <GuidedSignupCard v-if="showGuidedSignup" :headline="guidedSignupHeadline" />
        </div>

        <!-- Load more button -->
        <div v-if="showLoadMoreButton && !showGuidedSignup" class="flex justify-center mt-8 mb-9">
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
        v-if="showAnnounceModal"
        v-model:visible="showAnnounceModal"
        :initial-type="announceInitialType"
        :initial-instrument="announceInitialInstrument"
        :initial-styles="announceInitialStyles"
        :initial-location="announceInitialLocation"
        @created="handleAnnounceCreated"
    />
    <AuthRequiredModal
        v-if="showAuthModal"
        v-model:visible="showAuthModal"
        :variant="authModalVariant"
        :message="authModalMessage"
    />
</template>
<script setup>
defineOptions({ name: 'MusicianSearch' })

import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useMediaQuery, useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Chip from 'primevue/chip'
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import geocodingApi from '../../api/geocoding.js'
import searchApi from '../../api/search/musician.js'
import profileApi from '../../api/user/profile.js'
import CityAutoComplete from '../../components/Global/CityAutoComplete.vue'
import GuidedNoResults from '../../components/Search/Guided/GuidedNoResults.vue'
import GuidedSearch from '../../components/Search/Guided/GuidedSearch.vue'
import GuidedSignupCard from '../../components/Search/Guided/GuidedSignupCard.vue'
import { useUrlFilters } from '../../composables/useUrlFilters.js'
import { useInstrumentStore } from '../../store/attribute/instrument.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { useMusicianSearchStore } from '../../store/search/musician.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import {
  DEFAULT_RADIUS,
  GUIDED_MODE,
  signupHeadline,
  soughtLabel
} from '../../utils/guidedSearch.js'
import { profileCity } from '../../utils/profileLocation.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import MusicianAnnounceBlockItem from './MusicianAnnounceBlockItem.vue'

// Heavy modals loaded on demand (with v-if below) to keep them out of the initial bundle.
const AuthRequiredModal = defineAsyncComponent(
  () => import('../../components/Auth/AuthRequiredModal.vue')
)
const AddAnnounceModal = defineAsyncComponent(() => import('../User/Announce/AddAnnounceModal.vue'))

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

const quickSearch = ref('')
const quickSearchErrors = ref([])
const isSearching = ref(false)
const isFilterGenerating = ref(false)
const isSearchMade = ref(false)
const selectedInstrument = ref(null)
const selectedStyles = ref([])
const selectedLocation = ref(null)
const cityField = ref(null)
const isCityLoading = ref(false)
// Only the guided search bounds the distance (#1084); the filters sort by it and keep every one.
const selectedRadius = ref(null)
const selectSearchType = ref(null)
const selectSearchTypeOption = [
  { key: 2, name: 'Musiciens' },
  { key: 1, name: 'Groupe' }
]

// URL <-> primitive sync. The entity refs above stay the source of truth for
// the form (they back the v-model bindings); we mirror their derived
// primitives into useUrlFilters at the moment a search runs (autoSync: false
// so the URL captures the executed search, not in-progress draft state).
const {
  filters: urlFilters,
  clear: clearUrlFilters,
  push: pushUrlFilters
} = useUrlFilters(
  { type: '', instrument: '', styles: [], lat: '', lng: '', location: '', radius: '' },
  // `mode` kept as it is, so a guided search stays guided whatever the search writes to the address.
  { autoSync: false, preserveKeys: ['page', 'mode'] }
)

const showAnnounceModal = ref(false)
const createFromSearch = ref(false)
const showAuthModal = ref(false)
const authModalMessage = ref('')
const authModalVariant = ref('default')

// Progressive loading for guests: allow 3 pages (4 results × 3 = 12 results) before requiring login
const guestPagesLoaded = ref(1)
const MAX_GUEST_PAGES = 3

// Mobile responsive
const showMobileFilters = ref(false)
const isLargeScreen = useMediaQuery('(min-width: 1024px)')

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
  // Instruments and styles only feed the filter dropdowns — load them in
  // parallel rather than chaining two round-trips.
  const attributesLoaded = Promise.all([instrumentStore.loadInstruments(), styleStore.loadStyles()])

  if (isGuided.value) {
    await startGuided(attributesLoaded)
    return
  }

  const hasScopedSearch = !!prefilledInstrumentSlug.value || hasUrlFilters()

  if (hasScopedSearch) {
    // The initial search is scoped by a prefilled instrument or URL filters,
    // which are resolved against the attribute lists — load those first.
    await attributesLoaded
    initializeFiltersFromUrl()
    applyPrefilledInstrument()
    // A page such as « Rechercher un batteur » opened as is: its list is not a search anybody ran.
    await loadInitialResults({ landing: !hasUrlFilters() })
  } else {
    // Plain landing: the initial search is unscoped and needs neither list,
    // so run it alongside the attribute loads.
    await Promise.all([attributesLoaded, loadInitialResults()])
  }
})

function hasUrlFilters() {
  return (
    !!urlFilters.type ||
    !!urlFilters.instrument ||
    urlFilters.styles.length > 0 ||
    !!(urlFilters.lat && urlFilters.lng && urlFilters.location)
  )
}

async function loadInitialResults({ landing = false } = {}) {
  isSearching.value = true
  isSearchMade.value = true
  const params = { ...buildSearchParams(), landing }
  await musicianSearchStore.searchAnnounces(params)
  isSearching.value = false
}

function applyPrefilledInstrument() {
  // Only apply prefilled instrument from route meta if no URL instrument param
  if (urlFilters.instrument) {
    return
  }

  if (prefilledInstrumentSlug.value) {
    const instrument = instrumentStore.instruments.find(
      (i) => i.slug === prefilledInstrumentSlug.value
    )
    if (instrument) {
      selectedInstrument.value = instrument
    }
  }
}

// Watch for route changes to update pre-filled instrument
watch(prefilledInstrumentSlug, () => {
  applyPrefilledInstrument()
})

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

// Show "See more" button if:
// - Authenticated: as long as there are more results (lastBatchSize >= 12)
// - Guest: as long as there are more results (loadMore handles showing auth modal when limit reached)
const showLoadMoreButton = computed(() => {
  if (userSecurityStore.isAuthenticated) {
    return musicianSearchStore.lastBatchSize >= 12
  }
  return musicianSearchStore.lastBatchSize >= 4
})

// --- Guided mode (#1084) ---------------------------------------------------------------------------

const isGuided = computed(() => route.query.mode === GUIDED_MODE)
// Its questions need the instrument and style lists, so it shows once they are in.
const isGuidedReady = ref(false)
const guidedInitiallyAnswered = ref([])
const guidedComplete = ref(false)
const guidedWidening = ref(null)
// The search's key 1 is the bands' announces, which a musician looking for a band searches.
const guidedLookingForBand = computed(() => selectSearchType.value?.key === 1)
const guidedSought = computed(() =>
  soughtLabel({ lookingForBand: guidedLookingForBand.value, instrument: selectedInstrument.value })
)
const guidedSignupHeadline = computed(() =>
  signupHeadline({
    lookingForBand: guidedLookingForBand.value,
    instrument: selectedInstrument.value,
    styles: selectedStyles.value,
    location: selectedLocation.value
  })
)
// Where the modal opens on the filters page: the card takes the place of « Voir plus ».
const showGuidedSignup = computed(
  () =>
    isGuided.value &&
    !userSecurityStore.isAuthenticated &&
    guestPagesLoaded.value >= MAX_GUEST_PAGES &&
    showLoadMoreButton.value
)

/** Guided arrival: the questions the address already answers are skipped, the rest asked. */
async function startGuided(attributesLoaded) {
  await attributesLoaded
  initializeFiltersFromUrl()
  await prepareGuided()
}

/** The questions for what the page already holds, then the results for it. */
async function prepareGuided() {
  if (!selectSearchType.value)
    selectSearchType.value = selectSearchTypeOption.find((t) => t.key === 2)
  if (!selectedLocation.value) selectedLocation.value = await memberCity()
  if (selectedLocation.value && !selectedRadius.value) selectedRadius.value = DEFAULT_RADIUS
  guidedInitiallyAnswered.value = [
    selectedInstrument.value ? 'instrument' : null,
    selectedLocation.value ? 'location' : null,
    selectedStyles.value.length > 0 ? 'styles' : null
  ].filter(Boolean)
  guidedComplete.value = guidedInitiallyAnswered.value.length === 3
  isGuidedReady.value = true
  await loadInitialResults({ landing: !guidedComplete.value })
  loadGuidedWidening()
}

/** A logged in member's profile city (#1079), which answers « où ? » for them. */
async function memberCity() {
  if (!userSecurityStore.isAuthenticated) return null
  try {
    return profileCity(await profileApi.getMyProfile())
  } catch {
    return null
  }
}

// The view is kept alive between the search routes, so a guided address can reach an instance
// that mounted without it. Its filters are read once, at setup, so the questions start from what
// the page holds rather than from an address it would read stale.
watch(isGuided, async (guided) => {
  if (!guided || isGuidedReady.value) return
  await Promise.all([instrumentStore.loadInstruments(), styleStore.loadStyles()])
  await prepareGuided()
})

function handleGuidedAnswer({ complete }) {
  guidedComplete.value = complete
  search({ record: complete })
}

function handleGuidedToggleType() {
  selectSearchType.value = selectSearchTypeOption.find(
    (t) => t.key === (guidedLookingForBand.value ? 2 : 1)
  )
  search({ record: guidedComplete.value })
}

function handleGuidedWidenRadius(km) {
  selectedRadius.value = km
  search({ record: guidedComplete.value })
}

function handleGuidedAllStyles() {
  selectedStyles.value = []
  search({ record: guidedComplete.value })
}

// Asked only for an empty list: which wider search would show something.
async function loadGuidedWidening() {
  guidedWidening.value = null
  if (musicianSearchStore.announces.length > 0) return
  try {
    guidedWidening.value = await searchApi.getWidening(buildSearchParams())
  } catch {
    // Without it the empty state still offers to publish an announce.
  }
}

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
    params.location = selectedLocation.value.name
    // Only the guided search bounds the distance, even if an address kept a radius.
    if (isGuided.value && selectedRadius.value) params.radius = selectedRadius.value
  }
  return params
}

function initializeFiltersFromUrl() {
  // useUrlFilters already hydrated `urlFilters` from the URL on setup;
  // map those primitives back into the entity refs the form binds to.
  if (urlFilters.type) {
    selectSearchType.value = selectSearchTypeOption.find((t) => t.key === Number(urlFilters.type))
  }
  if (urlFilters.instrument) {
    selectedInstrument.value = instrumentStore.instruments.find(
      (i) => String(i.id) === urlFilters.instrument
    )
  }
  if (urlFilters.styles.length > 0) {
    selectedStyles.value = styleStore.styles.filter((s) => urlFilters.styles.includes(String(s.id)))
  }
  if (urlFilters.lat && urlFilters.lng && urlFilters.location) {
    selectedLocation.value = {
      latitude: Number.parseFloat(urlFilters.lat),
      longitude: Number.parseFloat(urlFilters.lng),
      name: urlFilters.location
    }
    const radius = Number.parseInt(urlFilters.radius, 10)
    selectedRadius.value = Number.isFinite(radius) ? radius : null
  }
}

function syncEntityRefsToUrlFilters() {
  urlFilters.type = selectSearchType.value ? String(selectSearchType.value.key) : ''
  urlFilters.instrument = selectedInstrument.value ? String(selectedInstrument.value.id) : ''
  urlFilters.styles = selectedStyles.value.map((s) => String(s.id))
  if (selectedLocation.value && typeof selectedLocation.value === 'object') {
    urlFilters.lat = String(selectedLocation.value.latitude)
    urlFilters.lng = String(selectedLocation.value.longitude)
    urlFilters.location = selectedLocation.value.name
    urlFilters.radius = selectedRadius.value ? String(selectedRadius.value) : ''
  } else {
    urlFilters.lat = ''
    urlFilters.lng = ''
    urlFilters.location = ''
    urlFilters.radius = ''
  }
}

/**
 * `record: false` for the guided search's partial answers (#1084): they refresh the results, but
 * only its final answer is a search somebody ran (#1075).
 */
async function search({ record = true } = {}) {
  // First, so the buttons are already disabled while the city below settles.
  isSearching.value = true
  // Typed but never picked, the city used to be dropped from the search without a word.
  await cityField.value?.pickFirstSuggestion()

  quickSearchErrors.value = []
  guestPagesLoaded.value = 1 // Reset on new search
  const searchFilters = {
    type: selectSearchType.value?.name || null,
    instrument: selectedInstrument.value?.musician_name || null,
    styles: selectedStyles.value.map((s) => s.name).join(', ') || null,
    location: selectedLocation.value?.name || null
  }
  trackUmamiEvent(
    isGuided.value ? 'musician-guided-search' : 'musician-search-submit',
    searchFilters
  )
  const params = { ...buildSearchParams(), landing: record !== true }
  await musicianSearchStore.searchAnnounces(params)
  isSearching.value = false
  isSearchMade.value = true

  // Sync filters with URL for shareability
  syncEntityRefsToUrlFilters()
  pushUrlFilters()

  if (musicianSearchStore.announces.length === 0) {
    trackUmamiEvent('musician-search-no-results', searchFilters)
  }
  if (isGuided.value) await loadGuidedWidening()
}

const isLoadingMore = ref(false)

async function loadMore() {
  const nextPage = musicianSearchStore.currentPage + 1
  trackUmamiEvent('musician-load-more', { page: nextPage })

  if (!userSecurityStore.isAuthenticated) {
    // Check if guest has reached the progressive loading limit
    if (guestPagesLoaded.value >= MAX_GUEST_PAGES) {
      openAuthModal('see_more')
      return
    }
  }

  isLoadingMore.value = true
  const params = buildSearchParams()
  params.page = nextPage
  params.append = true
  await musicianSearchStore.searchAnnounces(params)

  if (!userSecurityStore.isAuthenticated) {
    guestPagesLoaded.value++
  }

  isLoadingMore.value = false
}

function openAuthModal(variant, _musicianName = null) {
  authModalVariant.value = variant
  authModalMessage.value = ''
  showAuthModal.value = true
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
    if (e?.response?.status === 429) {
      quickSearchErrors.value = [
        'Trop de recherches effectuées. Veuillez réessayer dans quelques minutes.'
      ]
    } else if (e?.response?.status === 422) {
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
  quickSearch.value = ''
  selectSearchType.value = null
  // Reset results to initial state
  musicianSearchStore.clear()
  isSearchMade.value = false
  // Clear URL params
  clearUrlFilters()
  pushUrlFilters()
}

function handleOpenAnnounceModal() {
  trackUmamiEvent('musician-post-ad-click')
  if (!userSecurityStore.isAuthenticated) {
    openAuthModal('post_announce')
    return
  }
  createFromSearch.value = false
  showAnnounceModal.value = true
  trackUmamiEvent('announce-modal-opened', { fromSearch: false })
}

function handleOpenAnnounceModalFromSearch() {
  trackUmamiEvent('musician-post-ad-from-search-click')
  if (!userSecurityStore.isAuthenticated) {
    openAuthModal('post_announce')
    return
  }
  createFromSearch.value = true
  showAnnounceModal.value = true
  trackUmamiEvent('announce-modal-opened', { fromSearch: true })
}

async function handleAnnounceCreated() {
  createFromSearch.value = false
  // Refresh search results
  await search()
}

// Note: No onUnmounted cleanup - state is preserved by KeepAlive for back navigation
</script>
