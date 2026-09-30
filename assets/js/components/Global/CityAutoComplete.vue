<template>
  <AutoComplete
    v-model="city"
    :suggestions="suggestions"
    optionLabel="name"
    :input-id="inputId"
    :placeholder="placeholder"
    :delay="0"
    fluid
    @complete="search"
  >
    <template #option="{ option }">
      <div class="flex items-center gap-2">
        <i class="pi pi-map-marker text-primary" aria-hidden="true" />
        <div>
          <div class="font-medium">{{ option.name }}</div>
          <div v-if="option.context" class="text-sm text-surface-600 dark:text-surface-300">{{ option.context }}</div>
        </div>
      </div>
    </template>
  </AutoComplete>
</template>

<script setup>
import { useDebounceFn } from '@vueuse/core'
import AutoComplete from 'primevue/autocomplete'
import { ref } from 'vue'
import geocodingApi from '../../api/geocoding.js'
import { MIN_CITY_QUERY_LENGTH, resolveTypedCity } from '../../utils/cityPick.js'

/**
 * A city picked from Photon's suggestions: `{ name, context, latitude, longitude }` once chosen, the
 * typed text until then (#1074). `v-model:loading` is true while suggestions are on their way, so a
 * form can hold its submit until they land.
 */
defineProps({
  inputId: { type: String, default: undefined },
  placeholder: { type: String, default: 'Ville' }
})

const city = defineModel({ type: [Object, String], default: null })
const isLoading = defineModel('loading', { type: Boolean, default: false })
const suggestions = ref([])

// Only the answer to the latest query may land: an earlier, slower one would overwrite it.
let latestQuery = ''

async function lookup(query) {
  latestQuery = query
  isLoading.value = true
  let results = []
  try {
    results = await geocodingApi.searchCities(query)
  } catch (error) {
    console.error('Error searching location:', error)
  }
  if (query === latestQuery) {
    suggestions.value = results
    isLoading.value = false
  }
  return results
}

const debouncedLookup = useDebounceFn(lookup, 300)

function search(event) {
  if (event.query.length < MIN_CITY_QUERY_LENGTH) return
  // Loading, and the latest query, from the first keystroke rather than once the debounce fires: an
  // answer to an earlier query still in flight must not release submit or replace the suggestions.
  latestQuery = event.query
  isLoading.value = true
  debouncedLookup(event.query)
}

/** Settles typed text on the first suggestion before a search runs, and returns the resulting value. */
async function pickFirstSuggestion() {
  if (typeof city.value !== 'string') return city.value

  const typed = city.value.trim()
  if (typed.length < MIN_CITY_QUERY_LENGTH) return city.value

  const results =
    typed === latestQuery && !isLoading.value ? suggestions.value : await lookup(typed)
  // Typing went on during the lookup: its answer is for text that is no longer there.
  if (typeof city.value !== 'string' || city.value.trim() !== typed) return city.value

  // Returned from the local, not re-read from `city`: a model reads back the old prop until the
  // parent re-renders, which is after the caller has already used it.
  const resolved = resolveTypedCity(city.value, results)
  city.value = resolved
  return resolved
}

defineExpose({ pickFirstSuggestion })
</script>
