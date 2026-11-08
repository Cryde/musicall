<template>
  <!-- One root: as several, the layout's gap-8 fell between every block, a screen of air on a phone. -->
  <div class="flex flex-col gap-3 md:gap-8 max-md:pb-20">
    <div class="flex justify-end max-md:hidden">
        <breadcrumb :items="[{'label': breadcrumbLabel}]"/>
    </div>

    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 md:mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
            {{ h1Title }}
        </h1>

        <!-- On a phone it floats at the bottom instead, in reach and out of the way of the search. -->
        <Button
            :label="t('search.musician.post_announce')"
            icon="pi pi-megaphone"
            severity="info"
            size="small"
            class="w-full sm:w-auto max-md:hidden!"
            @click="handleOpenAnnounceModal"
        />
    </div>
    <Button
        :label="t('search.musician.post_announce')"
        icon="pi pi-megaphone"
        rounded
        class="md:hidden! fixed! right-5 bottom-[calc(1.5rem+env(safe-area-inset-bottom))] z-10 shadow-lg"
        @click="handleOpenAnnounceModal"
    />

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
    <div class="md:mb-6">
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
                :aria-label="t('search.musician.quick_search_aria_label')"
                :placeholder="t('search.musician.quick_search_placeholder')"
                class="w-full pl-12 pr-16 sm:pr-36 py-3 sm:py-4 text-base sm:text-lg rounded-2xl border-2 border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 focus:border-primary focus:ring-4 focus:ring-primary/20 outline-none transition-all text-surface-900 dark:text-surface-0 placeholder:text-surface-400"
                @keyup.enter="isQuickSearchParamEnough && generateQuickSearchFilters()"
            >
            <button
                @click="generateQuickSearchFilters"
                :disabled="isSearching || isFilterGenerating || !isQuickSearchParamEnough"
                :aria-label="t('search.musician.search')"
                class="absolute right-2 top-1/2 -translate-y-1/2 max-sm:w-10 max-sm:h-10 max-sm:justify-center sm:px-5 sm:py-2.5 bg-primary text-white rounded-xl flex items-center gap-2 hover:bg-primary-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
            >
                <i :class="isFilterGenerating ? 'pi pi-spin pi-spinner' : 'pi pi-search'" aria-hidden="true"></i>
                <span class="hidden sm:inline" aria-hidden="true">{{ t('search.musician.search') }}</span>
            </button>
        </div>

        <!-- Examples: one row of chips that scrolls sideways on a phone, so each is a finger wide. -->
        <div class="flex items-center gap-x-2 gap-y-1 mt-3 text-sm text-surface-600 dark:text-surface-400 md:flex-wrap max-md:overflow-x-auto max-md:-mx-6 max-md:px-6 [scrollbar-width:none]">
            <span class="shrink-0">{{ t('search.musician.try') }}</span>
            <button :class="EXAMPLE" @click="insertExample">{{ t('search.musician.example_guitarist') }}</button>
            <span class="text-surface-300 dark:text-surface-600 max-md:hidden" aria-hidden="true">•</span>
            <button :class="EXAMPLE" @click="insertExample">{{ t('search.musician.example_band') }}</button>
            <span class="text-surface-300 dark:text-surface-600 max-md:hidden" aria-hidden="true">•</span>
            <button :class="EXAMPLE" @click="insertExample">{{ t('search.musician.example_drummer') }}</button>
        </div>

        <!-- AI Warning -->
        <Message v-if="hasAutoFilledFields" size="small" severity="warn" variant="simple" class="mt-4">
            <i class="pi pi-info-circle mr-2" />
            {{ t('search.musician.ai_warning') }}
        </Message>
    </div>

    <Divider class="w-full my-0! max-md:hidden!"/>

    <!-- Type selector with clear descriptions -->
    <div class="max-md:mt-1">
        <p class="text-sm text-surface-600 dark:text-surface-400 mb-2 md:mb-3">{{ t('search.musician.looking_for') }}</p>
        <div :class="['grid grid-cols-2 gap-2 md:gap-3 max-w-lg transition-all duration-300 rounded-lg', autoFilledFields.type ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface-0 dark:ring-offset-surface-900' : '']">
            <button
                v-for="option in TYPE_CHOICES"
                :key="option.key"
                type="button"
                :aria-pressed="selectSearchType?.key === option.key"
                :class="[
                    'flex items-center gap-2.5 md:gap-3 min-w-0 max-md:h-14 px-3 md:p-3 rounded-xl border-2 text-left cursor-pointer transition-all',
                    selectSearchType?.key === option.key
                        ? 'border-primary bg-primary/10 dark:bg-primary/20'
                        : 'border-surface-300 dark:border-surface-700 bg-surface-50 dark:bg-transparent hover:border-surface-400 dark:hover:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-800'
                ]"
                @click="selectType(selectSearchTypeOption.find((type) => type.key === option.key))"
            >
                <span :class="['flex items-center justify-center shrink-0 md:w-10 md:h-10 rounded-full', selectSearchType?.key === option.key ? 'max-md:text-primary md:bg-primary md:text-white' : 'text-surface-600 dark:text-surface-300 md:bg-surface-200 md:dark:bg-surface-800']">
                    <i :class="[option.icon, 'text-lg']" aria-hidden="true" />
                </span>
                <span class="min-w-0">
                    <span class="block truncate font-medium text-surface-900 dark:text-surface-0">{{ t(option.label) }}</span>
                    <span class="block truncate text-xs text-surface-500 dark:text-surface-400">{{ t(option.hint) }}</span>
                </span>
            </button>
        </div>
    </div>

    <!-- Below lg the filters fold behind one button; they search as they change, no button needed. -->
    <div class="lg:hidden flex gap-2 md:mb-3">
        <Button
            :label="showMobileFilters ? t('search.musician.hide_filters') : filterToggleLabel"
            :icon="showMobileFilters ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
            icon-pos="right"
            severity="secondary"
            outlined
            class="flex-1 max-md:h-11 !border-surface-300 dark:!border-surface-600"
            :aria-expanded="showMobileFilters"
            @click="showMobileFilters = !showMobileFilters"
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
                    :placeholder="t('search.musician.instrument_placeholder')"
                    class="w-full lg:w-56"
                />
            </div>
            <div :class="['transition-all duration-300 rounded-lg w-full lg:w-auto', autoFilledFields.styles ? 'ring-2 ring-primary ring-offset-2 ring-offset-surface-0 dark:ring-offset-surface-900' : '']">
                <MultiSelect
                    v-model="selectedStyles"
                    :options="styleStore.styles"
                    :placeholder="t('search.musician.styles_placeholder')"
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
                    :placeholder="t('search.musician.city_placeholder')"
                    @blur="cityField?.pickFirstSuggestion()"
                />
            </div>
            <div class="w-full lg:w-auto">
                <Select
                    v-model="selectedLanguage"
                    :options="languageChoices"
                    optionLabel="name"
                    optionValue="code"
                    showClear
                    :placeholder="t('search.musician.language_placeholder')"
                    :aria-label="t('search.musician.language_placeholder')"
                    class="w-full lg:w-48"
                />
            </div>
            <!-- An icon in the filter row from lg, a worded button under the stacked fields below it. -->
            <div>
                <Button
                    text
                    icon="pi pi-times"
                    severity="secondary"
                    :label="isLargeScreen ? undefined : t('search.musician.clear_filters')"
                    :aria-label="t('search.musician.clear_filters')"
                    v-tooltip.bottom="isLargeScreen ? t('search.musician.clear_filters') : undefined"
                    @click="clearAllFilters"
                />
            </div>
        </div>
    </div>

    <!-- Active filters summary -->
    <div v-if="hasActiveFilters" ref="filtersSummary" class="flex items-center gap-2 md:mt-4 scroll-mt-20 md:flex-wrap max-md:overflow-x-auto max-md:-mx-6 max-md:px-6 [scrollbar-width:none] [&_.p-chip]:shrink-0">
        <span class="shrink-0 text-sm text-surface-500 dark:text-surface-400">{{ t('search.musician.active_filters') }}</span>
        <Chip
            v-if="selectSearchType"
            :label="selectSearchType.key === 2 ? t('search.musician.chip_musician') : t('search.musician.chip_band')"
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
        <Chip
            v-if="selectedLanguage"
            :label="t('search.musician.chip_language', { language: languageName(selectedLanguage) })"
            icon="pi pi-language"
            removable
            @remove="selectedLanguage = null"
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
                {{ t('search.musician.analysing_title') }}
            </h2>
            <p class="text-surface-600 dark:text-surface-300 text-center max-w-md mb-4">
                {{ t('search.musician.analysing_text') }}
            </p>
            <div class="flex items-center gap-3 text-sm text-surface-500 dark:text-surface-400">
                <div class="flex items-center gap-2">
                    <i class="pi pi-check-circle text-green-500" />
                    <span>{{ t('search.musician.analysing_step_read') }}</span>
                </div>
                <i class="pi pi-arrow-right" />
                <div class="flex items-center gap-2">
                    <i class="pi pi-spin pi-spinner text-primary" />
                    <span>{{ t('search.musician.analysing_step_criteria') }}</span>
                </div>
                <i class="pi pi-arrow-right hidden sm:inline" />
                <div class="hidden sm:flex items-center gap-2 text-surface-500 dark:text-surface-400">
                    <i class="pi pi-circle" />
                    <span>{{ t('search.musician.analysing_step_search') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading state (direct search) -->
    <div v-else-if="isSearching" class="mt-6">
        <p class="text-surface-600 dark:text-surface-300 mb-4">
            <i class="pi pi-spin pi-spinner mr-2" />
            {{ t('search.musician.searching') }}
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
        :where="selectedLocation?.name ? t('search.guided.around_city', { city: selectedLocation.name }) : ''"
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
                {{ t('search.musician.no_results_title') }}
            </h2>
            <p class="text-surface-600 dark:text-surface-300 text-center max-w-md mb-6">
                {{ t('search.musician.no_results_text') }}
            </p>
            <Button
                :label="t('search.musician.create_announce_with_criteria')"
                icon="pi pi-megaphone"
                severity="success"
                size="large"
                @click="handleOpenAnnounceModalFromSearch"
            />
            <p class="text-sm text-surface-500 dark:text-surface-400 mt-4">
                {{ t('search.musician.announce_visibility') }}
            </p>
        </div>
    </div>

    <!-- Results state -->
    <template v-else>
        <!-- Guided: the list follows each answer, and says so. -->
        <p v-if="isGuided" class="mt-6 mb-0 flex items-center gap-2 text-surface-700 dark:text-surface-300">
            <span class="w-2 h-2 rounded-full bg-teal-500" aria-hidden="true" />
            <i18n-t :keypath="guidedComplete ? 'search.guided.results_match' : 'search.guided.results_match_already'">
                <template #sought><strong class="text-surface-900 dark:text-surface-0">{{ guidedSought }}</strong></template>
            </i18n-t>
        </p>
        <div v-else-if="hasActiveFilters" class="flex flex-wrap items-center justify-end gap-4 md:mt-6">
            <Button
                :label="t('search.musician.create_announce_from_search')"
                icon="pi pi-plus"
                severity="success"
                size="small"
                class="max-md:w-full"
                @click="handleOpenAnnounceModalFromSearch"
            />
        </div>
        <!-- Dimmed while the filters' next answer comes in. A tap on a result is a member reading this
             search, which is when it counts as one (#1075). -->
        <div
            :class="['grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-8 md:mt-4 items-stretch transition-opacity', isRefreshing && 'opacity-60']"
            :aria-busy="isRefreshing"
            @click.capture="autoRun.settleNow()"
        >
            <MusicianAnnounceBlockItem
                v-for="announce in musicianSearchStore.announces"
                :key="announce.id"
                :announce-id="announce.id"
                :type="announce.type"
                :user="announce.user"
                :styles="announce.styles"
                :location_name="announce.location_name"
                :distance="announce.distance"
                :note="announce.note"
                :instrument="announce.instrument.name"
                :languages="announce.languages"
                :highlighted-styles="isGuided ? selectedStyles.map((style) => style.name) : []"
                from="search"
            />
            <GuidedSignupCard v-if="showGuidedSignup" :headline="guidedSignupHeadline" />
        </div>

        <!-- Load more button -->
        <div v-if="showLoadMoreButton && !showGuidedSignup" class="flex justify-center mt-2 md:mt-8 md:mb-9">
            <Button
                :label="userSecurityStore.isAuthenticated ? t('search.musician.see_more_results') : t('search.musician.see_more')"
                :icon="isLoadingMore ? 'pi pi-spin pi-spinner' : 'pi pi-arrow-down'"
                severity="secondary"
                size="large"
                :loading="isLoadingMore"
                @click="loadMore"
            />
        </div>
        <div v-else class="md:mb-9"></div>
    </template>

    <AddAnnounceModal
        v-if="showAnnounceModal"
        v-model:visible="showAnnounceModal"
        v-bind="announceInitialProps"
        @created="handleAnnounceCreated"
        @sign-in-required="handleAnnounceSignInRequired"
    />
    <!-- A message written before signing in, back after it (#1306). -->
    <SendMessageModal
        v-if="isResumedContactOpen"
        v-model:visible="isResumedContactOpen"
        :selected-recipient="resumedContact.recipient"
        :contact-origin="resumedContact.contactOrigin"
        :initial-content="resumedContact.content"
    />
    <AuthRequiredModal
        v-if="showAuthModal"
        v-model:visible="showAuthModal"
        :variant="authModalVariant"
        :message="authModalMessage"
    />
  </div>
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
import {
  computed,
  defineAsyncComponent,
  nextTick,
  onActivated,
  onDeactivated,
  onMounted,
  ref,
  watch
} from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import musicianAnnounceApi from '../../api/announce/musician.js'
import geocodingApi from '../../api/geocoding.js'
import searchApi from '../../api/search/musician.js'
import profileApi from '../../api/user/profile.js'
import CityAutoComplete from '../../components/Global/CityAutoComplete.vue'
import GuidedNoResults from '../../components/Search/Guided/GuidedNoResults.vue'
import GuidedSearch from '../../components/Search/Guided/GuidedSearch.vue'
import GuidedSignupCard from '../../components/Search/Guided/GuidedSignupCard.vue'
import { usePendingAction, usePendingActionResumer } from '../../composables/usePendingAction.js'
import { useUrlFilters } from '../../composables/useUrlFilters.js'
import { displayName } from '../../helper/user/displayName.js'
import { lastNavigationRestoredScroll } from '../../router/scrollPosition.js'
import { useInstrumentStore } from '../../store/attribute/instrument.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { useMusicianSearchStore } from '../../store/search/musician.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import {
  ANNOUNCE_CREATE,
  announceDraftPayload,
  announceModalProps
} from '../../utils/announceDraft.js'
import { isAnnounceLanguage, languageName, languageOptions } from '../../utils/announceLanguages.js'
import { contactAvailability, MESSAGE_SEND, readContactDraft } from '../../utils/contactDraft.js'
import {
  DEFAULT_RADIUS,
  GUIDED_MODE,
  signupHeadline,
  soughtLabel
} from '../../utils/guidedSearch.js'
import { profileCity } from '../../utils/profileLocation.js'
import { needsReveal } from '../../utils/revealTarget.js'
import { createSearchAutoRun } from '../../utils/searchAutoRun.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import MusicianAnnounceBlockItem from './MusicianAnnounceBlockItem.vue'

// Heavy modals loaded on demand (with v-if below) to keep them out of the initial bundle.
const AuthRequiredModal = defineAsyncComponent(
  () => import('../../components/Auth/AuthRequiredModal.vue')
)
const AddAnnounceModal = defineAsyncComponent(() => import('../User/Announce/AddAnnounceModal.vue'))
const SendMessageModal = defineAsyncComponent(
  () => import('../../components/Message/SendMessageModal.vue')
)

const { t } = useI18n()
const route = useRoute()

const styleStore = useStyleStore()
const instrumentStore = useInstrumentStore()
const musicianSearchStore = useMusicianSearchStore()
const userSecurityStore = useUserSecurityStore()

// The heading of each instrument landing page, by instrument slug
const INSTRUMENT_HEADING_KEYS = {
  guitare: 'search.musician.heading.guitarist',
  batterie: 'search.musician.heading.drummer',
  basse: 'search.musician.heading.bassist',
  chant: 'search.musician.heading.singer',
  piano: 'search.musician.heading.pianist'
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
const filtersSummary = ref(null)
const isCityLoading = ref(false)
// Only the guided search bounds the distance (#1084); the filters sort by it and keep every one.
const selectedRadius = ref(null)
// No language by default (#1198): a filter, so nothing disappears until a visitor asks for one.
const selectedLanguage = ref(null)
const languageChoices = computed(() => languageOptions())
const selectSearchType = ref(null)
const selectSearchTypeOption = [
  { key: 2, name: 'Musiciens' },
  { key: 1, name: 'Groupe' }
]
const TYPE_CHOICES = [
  {
    key: 2,
    icon: 'pi pi-user',
    label: 'search.musician.type_musician',
    hint: 'search.musician.type_musician_hint'
  },
  {
    key: 1,
    icon: 'pi pi-users',
    label: 'search.musician.type_band',
    hint: 'search.musician.type_band_hint'
  }
]
// A text link from md; on a phone a chip a finger can hit.
const EXAMPLE =
  'text-primary hover:underline cursor-pointer max-md:shrink-0 max-md:h-10 max-md:px-4 max-md:rounded-full max-md:border max-md:border-surface-300 max-md:dark:border-surface-600 max-md:whitespace-nowrap max-md:hover:no-underline'

// URL <-> primitive sync. The entity refs above stay the source of truth for
// the form (they back the v-model bindings); we mirror their derived
// primitives into useUrlFilters at the moment a search runs (autoSync: false
// so the URL captures the executed search, not in-progress draft state).
const {
  filters: urlFilters,
  clear: clearUrlFilters,
  push: pushUrlFilters
} = useUrlFilters(
  {
    type: '',
    instrument: '',
    styles: [],
    lat: '',
    lng: '',
    location: '',
    radius: '',
    language: ''
  },
  // `mode` kept as it is, so a guided search stays guided whatever the search writes to the address.
  { autoSync: false, preserveKeys: ['page', 'mode'] }
)

const showAnnounceModal = ref(false)
const createFromSearch = ref(false)
// The modal's props for an announce a visitor filled in before signing in (#1306), while it is open.
const resumedAnnounce = ref(null)
const isResumedContactOpen = ref(false)
const resumedContact = ref(null)
const pendingAction = usePendingAction()
const showAuthModal = ref(false)
const authModalMessage = ref('')
const authModalVariant = ref('default')

// Progressive loading for guests: allow 3 pages (4 results × 3 = 12 results) before requiring login
const guestPagesLoaded = ref(1)
const MAX_GUEST_PAGES = 3

// Mobile responsive
const showMobileFilters = ref(false)
const isLargeScreen = useMediaQuery('(min-width: 1024px)')
const activeFilterCount = computed(
  () =>
    [selectSearchType.value, selectedInstrument.value, selectedLanguage.value].filter(Boolean)
      .length +
    selectedStyles.value.length +
    (selectedLocation.value && typeof selectedLocation.value === 'object' ? 1 : 0)
)
const filterToggleLabel = computed(() =>
  activeFilterCount.value > 0
    ? t('search.musician.show_filters_count', { count: activeFilterCount.value })
    : t('search.musician.show_filters')
)

const prefilledInstrumentSlug = computed(() => route.meta.instrumentSlug || null)

const pageTitle = computed(() => {
  if (prefilledInstrumentSlug.value && INSTRUMENT_HEADING_KEYS[prefilledInstrumentSlug.value]) {
    return t('search.musician.page_title', {
      heading: t(INSTRUMENT_HEADING_KEYS[prefilledInstrumentSlug.value])
    })
  }
  return t('search.musician.page_title_default')
})

const h1Title = computed(() => {
  if (prefilledInstrumentSlug.value && INSTRUMENT_HEADING_KEYS[prefilledInstrumentSlug.value]) {
    return t(INSTRUMENT_HEADING_KEYS[prefilledInstrumentSlug.value])
  }
  return t('search.musician.heading.default')
})

const breadcrumbLabel = computed(() => {
  if (prefilledInstrumentSlug.value && INSTRUMENT_HEADING_KEYS[prefilledInstrumentSlug.value]) {
    return t(INSTRUMENT_HEADING_KEYS[prefilledInstrumentSlug.value])
  }
  return t('search.musician.heading.default')
})

// Not put back on unmount: the cached page unmounts after the next page has set its own title.
useTitle(pageTitle, { restoreOnUnmount: false })

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
    // A page such as « Rechercher un batteur » opened as is: its list is not a search anybody ran,
    // and its intro is the point, so it is not scrolled past either. Back or Forward to a search
    // restores where the visitor was, which must not be undone.
    await loadInitialResults({ landing: !hasUrlFilters() })
    if (hasUrlFilters() && !lastNavigationRestoredScroll()) await revealSearchResults()
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
    !!(urlFilters.lat && urlFilters.lng && urlFilters.location) ||
    !!urlFilters.language
  )
}

async function loadInitialResults({ landing = false } = {}) {
  markSearched()
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
// From one instrument page to another, the kept-alive page loads that instrument's list. Leaving for
// any other page also changes the slug, to nothing, which loads nothing.
watch(prefilledInstrumentSlug, (slug) => {
  if (!slug || isGuided.value) return
  applyPrefilledInstrument()
  loadInitialResults({ landing: true })
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
    (selectedLocation.value && typeof selectedLocation.value === 'object') ||
    selectedLanguage.value !== null
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

// key 2 = Musicien (searching for a musician) => announce type "musician" (looking for a musician)
// key 1 = Groupe (searching for a band) => announce type "band" (looking for a band)
const ANNOUNCE_TYPE_BY_SEARCH_KEY = { 2: 'musician', 1: 'band' }

// What the announce modal opens on: a draft kept over sign-in, the search's criteria, or nothing.
const announceInitialProps = computed(() => {
  if (resumedAnnounce.value) return { ...resumedAnnounce.value, isResumed: true }
  if (!createFromSearch.value) return {}
  return {
    initialType: ANNOUNCE_TYPE_BY_SEARCH_KEY[selectSearchType.value?.key] ?? null,
    initialInstrument: selectedInstrument.value,
    initialStyles: selectedStyles.value,
    initialLocation:
      selectedLocation.value && typeof selectedLocation.value === 'object'
        ? selectedLocation.value
        : null,
    initialLanguages: selectedLanguage.value ? [selectedLanguage.value] : []
  }
})

watch(showAnnounceModal, (isOpen) => {
  if (!isOpen) resumedAnnounce.value = null
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
  // The guided search asks no language, so it never filters on one an address kept.
  if (!isGuided.value && selectedLanguage.value) params.language = selectedLanguage.value
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
  // An unknown language would only get the search refused.
  if (isAnnounceLanguage(urlFilters.language)) {
    selectedLanguage.value = urlFilters.language
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
  urlFilters.language = selectedLanguage.value ?? ''
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
  // After the pick, which may have changed the filters: the search below is the one that counts.
  markSearched()

  quickSearchErrors.value = []
  guestPagesLoaded.value = 1 // Reset on new search
  const searchFilters = umamiSearchFilters()
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

// --- The filters search on their own -----------------------------------------------------------------
// Members did not press « Rechercher »: a change refreshes the results, and the search is recorded
// only once it settles, so the search log keeps searches, not every step on the way to one (#1075).

const autoRun = createSearchAutoRun({ refresh: refreshResults, record: recordSettledSearch })
const isRefreshing = ref(false)
const filtersKey = computed(() => JSON.stringify(buildSearchParams()))
// What the list on screen was searched with. Code that fills the filters itself (an address, an
// instrument page, the AI search) runs its own search right after, so the watch below sees nothing new.
let searchedKey = null
// Only the latest refresh lifts the dimming, as only its answer shows.
let refreshToken = 0
// Kept alive between pages: a hidden search must not search, nor write its filters onto another page.
const isActive = ref(true)
onActivated(() => {
  isActive.value = true
})
onDeactivated(() => {
  isActive.value = false
  autoRun.cancel()
})

function markSearched() {
  autoRun.cancel()
  refreshToken++
  isRefreshing.value = false
  searchedKey = filtersKey.value
}

async function refreshResults() {
  const token = ++refreshToken
  searchedKey = filtersKey.value
  isRefreshing.value = true
  let applied = false
  try {
    applied = await musicianSearchStore.searchAnnounces({ ...buildSearchParams(), landing: true })
  } finally {
    if (token === refreshToken) isRefreshing.value = false
  }
  if (!applied || !isActive.value) return
  isSearchMade.value = true
  guestPagesLoaded.value = 1
  syncEntityRefsToUrlFilters()
  pushUrlFilters()
}

function recordSettledSearch() {
  const searchFilters = umamiSearchFilters()
  trackUmamiEvent('musician-search-submit', searchFilters)
  if (musicianSearchStore.announces.length === 0) {
    trackUmamiEvent('musician-search-no-results', searchFilters)
  }
  musicianSearchStore.recordSearch(buildSearchParams()).catch(() => {
    // Best effort, as on the server: the member already has their results.
  })
}

// The guided mode and the AI search search themselves (a filter touched while the AI one works is
// overwritten by its answer anyway); a city counts once picked, not while it is typed.
watch(filtersKey, (key) => {
  if (!isActive.value || isGuided.value || isFilterGenerating.value || key === searchedKey) return
  autoRun.changed()
})

function umamiSearchFilters() {
  return {
    type: selectSearchType.value?.name || null,
    instrument: selectedInstrument.value?.musician_name || null,
    styles: selectedStyles.value.map((s) => s.name).join(', ') || null,
    location: selectedLocation.value?.name || null,
    language: selectedLanguage.value
  }
}

const isLoadingMore = ref(false)

async function loadMore() {
  // The page after the one on screen, so a refresh still on its way lands first.
  await autoRun.settleNow()
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
  const applied = await musicianSearchStore.searchAnnounces(params)

  if (applied && !userSecurityStore.isAuthenticated) {
    guestPagesLoaded.value++
  }

  isLoadingMore.value = false
}

function openAuthModal(variant, message = '') {
  authModalVariant.value = variant
  authModalMessage.value = message
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
    await revealSearchResults()
  } catch (e) {
    if (e?.response?.status === 429) {
      quickSearchErrors.value = [t('search.musician.error_too_many_searches')]
    } else if (e?.response?.status === 422) {
      quickSearchErrors.value = e.response.data.violations.map((violation) => violation.message)
    } else {
      quickSearchErrors.value = [t('search.musician.error_cannot_answer')]
    }
  }
  isFilterGenerating.value = false
}

/**
 * On a phone the search forms push what a search found below the fold. Lands on the active filters
 * rather than the list: they show what was understood, the results follow.
 */
async function revealSearchResults() {
  await nextTick()
  const target = filtersSummary.value
  if (!target || !needsReveal(target.getBoundingClientRect().top, window.innerHeight)) return

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' })
}

// Also a click handler, whose event must not read as « skip ».
function clearAllFilters(skipTracking = false) {
  if (skipTracking !== true) {
    trackUmamiEvent('musician-filter-clear')
  }
  quickSearchErrors.value = []
  selectedInstrument.value = null
  selectedStyles.value = []
  selectedLocation.value = null
  selectedLanguage.value = null
  quickSearch.value = ''
  selectSearchType.value = null
  // Clear URL params
  clearUrlFilters()
  pushUrlFilters()
  // The AI search fills the filters and searches itself; a member clearing them gets the opening list.
  if (skipTracking === true) {
    markSearched()
    return
  }
  loadInitialResults()
}

// A visitor fills it in too (#1306): only « Publier » asks them to sign in.
function handleOpenAnnounceModal() {
  trackUmamiEvent('musician-post-ad-click')
  createFromSearch.value = false
  showAnnounceModal.value = true
  trackUmamiEvent('announce-modal-opened', { fromSearch: false })
}

function handleOpenAnnounceModalFromSearch() {
  trackUmamiEvent('musician-post-ad-from-search-click')
  createFromSearch.value = true
  showAnnounceModal.value = true
  trackUmamiEvent('announce-modal-opened', { fromSearch: true })
}

async function handleAnnounceCreated() {
  createFromSearch.value = false
  // Refresh search results
  await search()
}

function handleAnnounceSignInRequired(values) {
  const isKept = pendingAction.keep({
    kind: ANNOUNCE_CREATE,
    payload: announceDraftPayload(values),
    stoppedOnSubmit: true
  })
  // Promised only when kept: a browser that blocks storage gets the usual prompt.
  openAuthModal('post_announce', isKept ? t('search.pending.announce_sign_in') : '')
}

// --- Resumed after sign-in (#1306): reopened prefilled, never sent on their own -----------------------

const CONTACT_UNAVAILABLE_KEYS = {
  announce_gone: 'search.pending.contact_announce_gone',
  recipient_deleted: 'search.pending.contact_recipient_deleted',
  own_announce: 'search.pending.contact_own_announce'
}

usePendingActionResumer(ANNOUNCE_CREATE, resumeAnnounce)
usePendingActionResumer(MESSAGE_SEND, resumeContact)

async function resumeAnnounce(payload) {
  if (instrumentStore.instruments.length === 0 || styleStore.styles.length === 0) {
    await Promise.all([instrumentStore.loadInstruments(), styleStore.loadStyles()])
  }
  const initialProps = announceModalProps(payload, {
    instruments: instrumentStore.instruments,
    styles: styleStore.styles
  })

  return {
    label: t('search.pending.announce_label'),
    resume: () => {
      createFromSearch.value = false
      resumedAnnounce.value = initialProps
      showAnnounceModal.value = true
    }
  }
}

async function resumeContact(payload) {
  const draft = readContactDraft(payload)
  const announce = draft ? await announceStillOnline(draft.announceId) : null
  const availability = contactAvailability({
    announce,
    recipientId: draft?.recipientId,
    signedInUsername: userSecurityStore.user?.username ?? null
  })
  if (availability !== 'available') {
    return { unavailable: t(CONTACT_UNAVAILABLE_KEYS[availability]) }
  }

  return {
    label: t('search.pending.contact_label', { name: displayName(announce.author) }),
    resume: () => {
      resumedContact.value = {
        recipient: announce.author,
        contactOrigin: { musicianAnnounceId: draft.announceId },
        content: draft.content
      }
      isResumedContactOpen.value = true
    }
  }
}

/** The announce, or null once it is deleted. Any other failure is thrown, so the draft is kept for later. */
async function announceStillOnline(id) {
  try {
    return await musicianAnnounceApi.getItem(id)
  } catch (error) {
    if (error?.response?.status === 404) return null
    throw error
  }
}

// Note: No onUnmounted cleanup - state is preserved by KeepAlive for back navigation
</script>
