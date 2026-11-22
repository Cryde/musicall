<template>
  <!-- « Correspond à ta recherche : basse, débutant, à 2,4 km de Bruxelles », the values in bold. -->
  <p
    v-if="matches.length"
    class="inline-flex items-start gap-2 rounded-lg px-3 py-1.5 text-sm bg-green-50 text-green-700 dark:bg-green-400/10 dark:text-green-300"
  >
    <i class="pi pi-check-circle text-xs mt-1" aria-hidden="true" />
    <span>{{ t('profile.teacher_results.match') }}<template v-for="(match, index) in matches" :key="match.key">{{ index > 0 ? ', ' : ' ' }}<i18n-t
      v-if="match.key === 'distance'"
      keypath="profile.teacher_results.match_distance"
      scope="global"
    ><template #place><DistanceFromCity :distance="match.distance" :city="match.city" /></template></i18n-t><strong
      v-else
      class="font-semibold"
    >{{ match.label }}</strong></template></span>
  </p>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import DistanceFromCity from './DistanceFromCity.vue'

/** Why a teacher opened from the search is in its results (#1315). */
defineProps({
  /** `{ key, label }`, or `{ key: 'distance', distance, city }`: the matches useTeacherSearchResults() words. */
  matches: { type: Array, required: true }
})

const { t } = useI18n()
</script>
