<template>
    <div class="flex justify-end">
        <breadcrumb :items="[{'label':  'Rechercher un musicien'}]"/>
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
            />
        </div>
    </div>

    <div class="flex flex-wrap w-full gap-4 items-center">
        <InputGroup class="md:w-80">
            <InputText
                v-model="quickSearch"
                fluid
                size="large"
                placeholder="Taper votre recherche ici, exemple: Je recherche un guitariste qui joue du rock"
            />
            <Button
                label="Recherche rapide"
                icon="pi pi-search"
                severity="info"
                :disabled="isSearching"
            />
        </InputGroup>

        <Message size="small" severity="secondary" variant="simple">
            exemples :
            <span class="italic font-bold cursor-pointer hover:text-sky-300" @click="insertExample">je cherche un groupe de pop et rock qui a besoin d'un batteur</span>,
            <span class="italic font-bold cursor-pointer hover:text-sky-300" @click="insertExample">je recherche un guitariste pour mon groupe de funk</span>,
            <span class="italic font-bold cursor-pointer hover:text-sky-300" @click="insertExample">je recherche un chanteur pour mon groupe de stoner et métal</span>
        </Message>
    </div>

    <Divider class="w-full my-0!" />

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
            class="w-full md:w-70" />
        <MultiSelect
            v-model="selectedStyles"
            :options="styleStore.styles"
            placeholder="Style"
            option-label="name"
            filter showClear
            class="flex-auto lg:flex-1 lg:mt-0 w-full lg:w-72 mr-0 lg:mr-6 text-surface-900 dark:text-surface-0"
        />

        <Button
            severity="secondary"
            icon="pi pi-search"
            :disabled="!isSearchParamEnough || isSearching"
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

    <div v-if="!isSearchMade.value && musicianSearchStore.announces.length === 0" class="flex content-center justify-center items-center h-50">
        <Message size="large" icon="pi pi-filter">
            <div class="ml-4">
                Cherchez parmis + de 2000 annonces des musiciens ou groupes. <br/>
            Sélectionnez vos filtres ci-dessus pour effectuer la recherche parmi les musiciens ou groupes.
            </div>
        </Message>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mt-8 mb-9" v-else>
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
<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import InputGroup from 'primevue/inputgroup'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useInstrumentStore } from '../../store/attribute/instrument.js'
import { useStyleStore } from '../../store/attribute/style.js'
import { useMusicianSearchStore } from '../../store/search/musician.js'
import Breadcrumb from '../Global/Breadcrumb.vue'
import MusicianAnnounceBlockItem from './MusicianAnnounceBlockItem.vue'

useTitle('Rechercher un musicien ou un groupe - MusicAll')

const styleStore = useStyleStore()
const instrumentStore = useInstrumentStore()
const musicianSearchStore = useMusicianSearchStore()

onMounted(async () => {
  await instrumentStore.loadInstruments()
  await styleStore.loadStyles()
})

const quickSearch = ref('')
const isSearching = ref(false)
const isSearchMade = ref(false)
const selectedInstrument = ref(null)
const selectedStyles = ref([])
const selectSearchType = ref({ key: 1, name: 'Musiciens' })
const selectSearchTypeOption = [
  { key: 1, name: 'Musiciens' },
  { key: 2, name: 'Groupe' }
]

const isSearchParamEnough = computed(() => {
  return selectedInstrument.value !== null && selectSearchType.value !== null
})

function insertExample(e) {
  search.value = e.target.textContent
}

async function search() {
  isSearching.value = true
  await musicianSearchStore.searchAnnounces({
    type: selectSearchType.value.key,
    instrument: selectedInstrument.value.id,
    styles: selectedStyles?.value.map((style) => style.id)
  })
  isSearching.value = false
}

function clearAllFilters() {
  selectedInstrument.value = null
  selectedStyles.value = []
  search.value = ''
  selectSearchType.value = { key: 1, name: 'Musiciens' }
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
  selectSearchType.value = { key: 1, name: 'Musiciens' }
})
</script>
