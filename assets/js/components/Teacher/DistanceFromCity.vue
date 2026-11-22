<template>
  <i18n-t keypath="profile.teacher_results.distance_from" scope="global" tag="span">
    <template #distance><strong class="font-semibold">{{ distanceText }}</strong></template>
    <template #city>{{ cityText }}</template>
  </i18n-t>
</template>

<script setup>
import { computed } from 'vue'
import { intlLocale } from '../../i18n/formatLocale.js'
import { cityOf, formatDistance } from '../../utils/teacherSearch.js'

/** « 2,4 km de Bruxelles », the distance in bold, from the city the teacher search was around (#1315). */
const props = defineProps({
  distance: { type: Number, required: true },
  city: { type: String, required: true }
})

const distanceText = computed(() => formatDistance(props.distance, intlLocale()))
const cityText = computed(() => cityOf(props.city, intlLocale()))
</script>
