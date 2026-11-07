<template>
  <section
    v-if="instruments.length"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    aria-labelledby="profile-instruments-title"
  >
    <h2 id="profile-instruments-title" class="m-0 mb-1 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.musician_page.instruments') }}
    </h2>
    <ul class="list-none m-0 p-0 divide-y divide-surface-200 dark:divide-surface-700">
      <li v-for="instrument in instruments" :key="instrument.instrument_id" class="flex items-center gap-3 py-2.5">
        <span class="flex-1 min-w-0 text-sm text-surface-900 dark:text-surface-0 truncate">{{ instrument.instrument_name }}</span>
        <span class="flex gap-0.5" aria-hidden="true">
          <i
            v-for="step in SKILL_STEP_COUNT"
            :key="step"
            class="block w-4 h-1.5 rounded-sm"
            :class="step <= skillSteps(instrument.skill_level) ? 'bg-primary' : 'bg-surface-200 dark:bg-surface-700'"
          />
        </span>
        <span class="w-24 text-right text-xs text-surface-600 dark:text-surface-300">{{ instrument.skill_level_label }}</span>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { SKILL_STEP_COUNT, skillSteps } from '../../../utils/skillLevel.js'

/** The instruments a musician plays, each with their level in four bars (#1317). */
defineProps({
  instruments: { type: Array, default: () => [] }
})

const { t } = useI18n()
</script>
