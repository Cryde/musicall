<template>
    <div class="flex justify-end">
        <breadcrumb :items="[{'label': 'Rechercher un musicien'}]"/>
    </div>

    <div class="flex justify-between mb-10">
        <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
            Rechercher un musicien
        </h1>

        <div class="flex flex-wrap justify-end-safe gap-4">
            <Button
                label="Poster une annonce"
                icon="pi pi-megaphone"
                severity="info"
                size="small"
                class="whitespace-nowrap"
                @click="handleOpenAnnounceModal"
            />
        </div>
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
            <span class="italic font-bold cursor-pointer hover:text-sky-300" @click="insertExample">je recherche un chanteur pour mon groupe de stoner et métal</span>
        </Message>
    </div>

    <Divider class="w-full my-0!"/>

    <div class="flex flex-wrap gap-4 items-center">
        <SelectButton
            v-model="selectSearchType"
            :options="selectSearchTypeOption"
            optionLabel="name"
        />
        <Select
            v-model="selectedInstrument"
            :options="instrumentStore.instruments"
            filter
            optionLabel="musician_name"
            placeholder="Sélectionnez un instrument"
            class="w-full md:w-70"/>
        <MultiSelect
            v-model="selectedStyles"
            :options="styleStore.styles"
            placeholder="Style"
            option-label="name"
            filter showClear
            class="flex-auto lg:flex-1 lg:mt-0 w-full lg:w-72 text-surface-900 dark:text-surface-0"
        />
        <AutoComplete
            v-model="selectedLocation"
            :suggestions="locationSuggestions"
            optionLabel="name"
            placeholder="Ville (optionnel)"
            class="w-full md:w-56 mr-0 lg:mr-6"
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

        <Button
            severity="secondary"
            icon="pi pi-search"
            :disabled="!isSearchParamEnough || isSearching || isFilterGenerating"
            label="Rechercher"
            class="text-surface-500 dark:text-surface-400 shrink-0"
            @click="search"
        />
        <Button
            text
            icon="pi pi-times"
            severity="secondary"
            label="Reset les filtres"
            class="text-surface-500 dark:text-surface-400 shrink-0"
            @click="clearAllFilters"
        />
    </div>

    <div v-if="!isSearchMade.value && musicianSearchStore.announces.length === 0"
         class="flex content-center justify-center items-center h-50">
        <Message size="large" icon="pi pi-filter">
            <div class="ml-4">
                Cherchez parmis + de 2000 annonces des musiciens ou groupes. <br/>
                Sélectionnez vos filtres ci-dessus pour effectuer la recherche parmi les musiciens ou groupes.
            </div>
        </Message>
    </div>
    <template v-else>
        <div class="flex justify-end mt-6">
            <Button
                label="Creer une annonce depuis cette recherche"
                icon="pi pi-plus"
                severity="success"
                size="small"
                @click="handleOpenAnnounceModalFromSearch"
            />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mt-4 mb-9">
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
import { useDebounceFn, useTitle } from '@vueuse/core'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import geocodingApi from '../../api/geocoding.js'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import { useInstrumentStore } from '../../store/attribute/instrument.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { useMusicianSearchStore } from '../../store/search/musician.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import AddAnnounceModal from '../User/Announce/AddAnnounceModal.vue'
import MusicianAnnounceBlockItem from './MusicianAnnounceBlockItem.vue'

useTitle('Rechercher un musicien ou un groupe - MusicAll')

const styleStore = useStyleStore()
const instrumentStore = useInstrumentStore()
const musicianSearchStore = useMusicianSearchStore()
const userSecurityStore = useUserSecurityStore()

onMounted(async () => {
  await instrumentStore.loadInstruments()
  await styleStore.loadStyles()
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
const selectSearchType = ref({ key: 1, name: 'Musiciens' })
const selectSearchTypeOption = [
  { key: 1, name: 'Musiciens' },
  { key: 2, name: 'Groupe' }
]

const showAnnounceModal = ref(false)
const createFromSearch = ref(false)
const showAuthModal = ref(false)
const authModalMessage = ref('')

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

const isSearchParamEnough = computed(() => {
  return selectedInstrument.value !== null && selectSearchType.value !== null
})

const isQuickSearchParamEnough = computed(() => {
  return quickSearch.value !== '' && quickSearch.value.length > 4
})

// Computed values for announce modal initial values (only when creating from search)
const announceInitialType = computed(() => {
  if (!createFromSearch.value) return null
  // key 1 = Musiciens (searching for a musician) => announce type "musician" (looking for a musician)
  // key 2 = Groupe (searching for a band) => announce type "band" (looking for a band)
  if (!selectSearchType.value) return null
  return selectSearchType.value.key === 1 ? 'musician' : 'band'
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

async function search() {
  quickSearchErrors.value = []
  isSearching.value = true
  const params = {
    type: selectSearchType.value.key,
    instrument: selectedInstrument.value.id,
    styles: selectedStyles?.value.map((style) => style.id)
  }
  if (selectedLocation.value && typeof selectedLocation.value === 'object') {
    params.latitude = selectedLocation.value.latitude
    params.longitude = selectedLocation.value.longitude
  }
  await musicianSearchStore.searchAnnounces(params)
  isSearching.value = false
}

async function generateQuickSearchFilters() {
  const searchTxt = quickSearch.value
  clearAllFilters()
  quickSearch.value = searchTxt
  quickSearchErrors.value = []
  isFilterGenerating.value = true
  try {
    await musicianSearchStore.getSearchAnnouncesFilters({ search: searchTxt })
    selectSearchType.value = selectSearchTypeOption.find(
      (type) => type.key === musicianSearchStore.filters.type
    )
    selectedInstrument.value = instrumentStore.instruments.find(
      (i) => i.id === musicianSearchStore.filters.instrument
    )
    if (musicianSearchStore.filters.styles.length) {
      selectedStyles.value = styleStore.styles.filter((style) =>
        musicianSearchStore.filters.styles.includes(style.id)
      )
    }
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

function clearAllFilters() {
  quickSearchErrors.value = []
  selectedInstrument.value = null
  selectedStyles.value = []
  selectedLocation.value = null
  locationSuggestions.value = []
  quickSearch.value = ''
  selectSearchType.value = { key: 1, name: 'Musiciens' }
}

function handleOpenAnnounceModal() {
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
  // Refresh search results if a search was made
  if (isSearchParamEnough.value) {
    await search()
  }
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
  selectSearchType.value = { key: 1, name: 'Musiciens' }
})
</script>
