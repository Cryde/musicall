<template>
  <AutoComplete
    v-model="city"
    :suggestions="suggestions"
    optionLabel="name"
    :input-id="inputId"
    :placeholder="placeholder"
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

/**
 * A city picked from Photon's suggestions: `{ name, context, latitude, longitude }` once chosen, the
 * typed text until then (#1074).
 */
defineProps({
  inputId: { type: String, default: undefined },
  placeholder: { type: String, default: 'Ville' }
})

const city = defineModel({ type: [Object, String], default: null })
const suggestions = ref([])

const MIN_QUERY_LENGTH = 2

const debouncedSearch = useDebounceFn(async (query) => {
  try {
    suggestions.value = await geocodingApi.searchCities(query)
  } catch (error) {
    console.error('Error searching location:', error)
    suggestions.value = []
  }
}, 300)

function search(event) {
  if (event.query.length >= MIN_QUERY_LENGTH) debouncedSearch(event.query)
}
</script>
