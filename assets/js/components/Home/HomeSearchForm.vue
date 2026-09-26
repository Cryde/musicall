<template>
  <form
    class="flex flex-col gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0/90 dark:bg-surface-900/85 p-3 sm:p-4 shadow-xl backdrop-blur-sm"
    role="search"
    aria-label="Rechercher un musicien ou un groupe"
    @submit.prevent="submit"
  >
    <SelectButton
      v-model="lookingFor"
      :options="LOOKING_FOR_OPTIONS"
      option-label="label"
      option-value="value"
      :allow-empty="false"
      aria-label="Ce que vous cherchez"
      class="self-start max-w-full"
    />

    <div class="flex flex-col md:flex-row gap-3 md:items-end">
      <div class="flex flex-col gap-1 flex-1 min-w-0">
        <label id="home-search-instrument" class="text-xs font-semibold uppercase tracking-wide text-surface-600 dark:text-surface-300">
          Instrument
        </label>
        <Select
          v-model="instrument"
          :options="instrumentStore.instruments"
          option-label="musician_name"
          filter
          show-clear
          placeholder="Tous les instruments"
          aria-labelledby="home-search-instrument"
          :loading="instrumentStore.instruments.length === 0"
          class="w-full"
        />
      </div>
      <div class="flex flex-col gap-1 flex-1 min-w-0">
        <label for="home-search-city" class="text-xs font-semibold uppercase tracking-wide text-surface-600 dark:text-surface-300">
          Où
        </label>
        <CityAutoComplete v-model="city" input-id="home-search-city" placeholder="Ville (optionnel)" />
      </div>
      <Button type="submit" label="Rechercher" icon="pi pi-search" severity="info" class="md:h-[2.625rem] shrink-0" />
    </div>
  </form>
</template>

<script setup>
import Button from 'primevue/button'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useInstrumentStore } from '../../store/attribute/instrument.js'
import {
  LOOKING_FOR_BAND,
  LOOKING_FOR_MUSICIAN,
  musicianSearchRoute
} from '../../utils/homeSearch.js'
import CityAutoComplete from '../Global/CityAutoComplete.vue'

/** The homepage's way into the musician search (#1074): it only builds the search page's link. */
const LOOKING_FOR_OPTIONS = [
  { label: 'Je cherche un musicien', value: LOOKING_FOR_MUSICIAN },
  { label: 'Je cherche un groupe', value: LOOKING_FOR_BAND }
]

const router = useRouter()
const instrumentStore = useInstrumentStore()

const lookingFor = ref(LOOKING_FOR_MUSICIAN)
const instrument = ref(null)
const city = ref(null)

onMounted(() => {
  if (instrumentStore.instruments.length === 0) instrumentStore.loadInstruments()
})

function submit() {
  router.push(
    musicianSearchRoute({
      lookingFor: lookingFor.value,
      instrument: instrument.value,
      city: city.value
    })
  )
}
</script>
