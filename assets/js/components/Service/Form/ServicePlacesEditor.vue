<template>
  <ServiceFormSection icon="pi pi-map-marker" :title="t('profile.service_form.section_places')" class="gap-4">
    <div v-for="key in LOCATED" :key="key" class="flex flex-col gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
      <label class="inline-flex items-center gap-2 font-medium text-surface-900 dark:text-surface-0">
        <Checkbox v-model="places[key].enabled" binary :disabled="disabled" />
        {{ t(PLACE_LABELS[key]) }}
      </label>
      <template v-if="places[key].enabled">
        <label :for="`${idPrefix}-city-${key}`" class="sr-only">{{ t('profile.service_form.city') }}</label>
        <CityAutoComplete
          v-model="citySearch[key]"
          :input-id="`${idPrefix}-city-${key}`"
          :placeholder="t('profile.teacher.edit.city_placeholder')"
          :disabled="disabled"
        />
        <InputText
          v-if="key === 'studio'"
          v-model="places.studio.address"
          :placeholder="t('profile.teacher.edit.address_placeholder')"
          :aria-label="t('profile.service_form.address')"
          :disabled="disabled"
        />
        <div v-else class="flex items-center gap-2">
          <InputNumber fluid v-model="places.client.radius" :min="1" :max="500" suffix=" km" :aria-label="t('profile.service_form.radius')" :disabled="disabled" class="w-36" />
          <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.teacher.edit.radius_suffix') }}</span>
        </div>
      </template>
    </div>
    <!-- What only one kind offers, such as working remotely -->
    <slot />
  </ServiceFormSection>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import { reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import CityAutoComplete from '../../Global/CityAutoComplete.vue'
import ServiceFormSection from './ServiceFormSection.vue'

/** « Où » in a service's form (#1410, #1411): the member's studio, and how far they travel. */
defineProps({
  /** Prefixes the field ids, unique on the page. */
  idPrefix: { type: String, required: true },
  disabled: { type: Boolean, default: false }
})
/** From placesForm(); a new object (the form loaded) fills the city fields again. */
const places = defineModel('places', { type: Object, required: true })

/** The two located places, by their key in placesForm(). */
const LOCATED = ['studio', 'client']
const PLACE_LABELS = {
  studio: 'profile.service_form.place_studio',
  client: 'profile.service_form.place_client'
}

const { t } = useI18n()

/** The city fields' own values: the typed text until a city is picked, then `{ name, context, latitude, longitude }`. */
const citySearch = reactive({ studio: null, client: null })

function cityValue(place) {
  return place.city
    ? {
        name: place.city,
        context: place.country,
        latitude: place.latitude,
        longitude: place.longitude
      }
    : null
}

watch(
  () => places.value,
  (value) => {
    for (const key of LOCATED) citySearch[key] = cityValue(value[key])
  },
  { immediate: true }
)

// A city counts once picked; emptying the field clears it.
watch(citySearch, () => {
  for (const key of LOCATED) {
    const city = citySearch[key]
    if (city && typeof city === 'object') {
      Object.assign(places.value[key], {
        city: city.name,
        country: city.context?.split(', ').pop() || '',
        latitude: city.latitude,
        longitude: city.longitude
      })
    } else if (!city) {
      Object.assign(places.value[key], { city: '', country: '', latitude: null, longitude: null })
    }
  }
})
</script>
