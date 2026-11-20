<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center gap-3 text-sm">
      <span class="text-surface-500 dark:text-surface-400 tabular-nums">{{ speedPercent(SPEED.MIN) }}</span>
      <Slider
        :model-value="speed"
        :min="SPEED.MIN"
        :max="SPEED.MAX"
        :step="SPEED.STEP"
        class="flex-1"
        :aria-label="t('band_space_tabs.speed.label')"
        @update:model-value="emit('update:speed', $event)"
      />
      <b class="w-12 text-right tabular-nums">{{ speedPercent(speed) }}</b>
    </div>
    <div class="flex gap-1.5" role="group" :aria-label="t('band_space_tabs.speed.presets')">
      <Button
        v-for="preset in PRESETS"
        :key="preset"
        :label="speedPercent(preset)"
        size="small"
        class="flex-1"
        :severity="preset === speed ? undefined : 'secondary'"
        :outlined="preset !== speed"
        :aria-pressed="preset === speed"
        @click="emit('update:speed', preset)"
      />
    </div>
    <label
      class="flex items-center justify-between gap-3 text-sm cursor-pointer min-h-10"
      :title="t('band_space_tabs.speed.trainer_hint')"
    >
      <span class="flex items-center gap-2">
        <i class="pi pi-angle-double-up text-surface-500" aria-hidden="true" />
        {{ t('band_space_tabs.speed.trainer') }}
      </span>
      <ToggleSwitch :model-value="trainer" @update:model-value="emit('toggle-trainer')" />
    </label>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Slider from 'primevue/slider'
import ToggleSwitch from 'primevue/toggleswitch'
import { useI18n } from 'vue-i18n'
import { useTabLabels } from '../../../composables/useTabLabels.js'
import { SPEED } from '../../../utils/tabPlayback.js'

/** Speed from 50 to 100 %, the usual steps, and the trainer that adds 5 % on each pass of a loop. */
defineProps({
  speed: { type: Number, required: true },
  trainer: { type: Boolean, required: true }
})
const emit = defineEmits(['update:speed', 'toggle-trainer'])
const { t } = useI18n()
const { speedPercent } = useTabLabels()

const PRESETS = [0.5, 0.75, 0.9, 1]
</script>
