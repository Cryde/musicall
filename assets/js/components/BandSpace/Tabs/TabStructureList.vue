<template>
  <ol class="list-none m-0 px-2 pb-2 flex flex-col gap-0.5">
    <li
      v-for="(section, k) in sections"
      :key="k"
      class="flex items-center rounded-lg"
      :class="
        k === loopedSection
          ? 'bg-primary-50 dark:bg-primary-500/15'
          : k === currentSection
            ? 'bg-surface-100 dark:bg-surface-800'
            : ''
      "
      :aria-current="k === currentSection ? 'step' : undefined"
    >
      <button
        type="button"
        class="flex-1 min-w-0 flex items-center gap-2.5 px-2 py-1.5 border-0 bg-transparent text-left cursor-pointer rounded-lg"
        :aria-label="t('band_space_tabs.section.go', { name: sectionName(section) })"
        @click="emit('go', k)"
      >
        <span class="tab-section-letter tab-section-hue" :style="{ '--hue': sectionHue(section.letter) }">{{ section.letter || '·' }}</span>
        <span class="flex flex-col min-w-0 leading-tight">
          <span class="text-sm font-semibold truncate text-surface-900 dark:text-surface-0">
            {{ sectionName(section) }}
            <span v-if="k === currentSection" class="text-red-500 text-[0.6rem] align-[2px]" aria-hidden="true">●</span>
          </span>
          <span class="text-xs text-surface-500 dark:text-surface-400 tabular-nums">
            {{ sectionBars(section) }} · {{ formatDuration(section.seconds) }}
          </span>
        </span>
      </button>
      <Button
        icon="pi pi-replay"
        text
        rounded
        size="small"
        :severity="k === loopedSection ? undefined : 'secondary'"
        :variant="k === loopedSection ? undefined : 'text'"
        :aria-label="t('band_space_tabs.section.loop', { name: sectionName(section) })"
        :aria-pressed="k === loopedSection"
        class="mr-1 shrink-0"
        :class="k === loopedSection ? 'bg-primary! text-primary-contrast!' : ''"
        @click="emit('loop', k)"
      />
    </li>
  </ol>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { useTabLabels } from '../../../composables/useTabLabels.js'
import { formatDuration } from '../../../utils/setlistDuration.js'
import { sectionHue } from '../../../utils/tabSections.js'
import './tabSectionHue.css'

/** The song's sections, from the file's markers: a click goes there, the loop button loops it. */
defineProps({
  sections: { type: Array, required: true },
  currentSection: { type: Number, default: -1 },
  loopedSection: { type: Number, default: -1 }
})
const emit = defineEmits(['go', 'loop'])
const { t } = useI18n()
const { sectionName, sectionBars } = useTabLabels()
</script>

<style>
/* A section's letter on its colour (tabSectionHue.css). */
.tab-section-letter {
  width: 1.625rem;
  height: 1.625rem;
  border-radius: 0.375rem;
  flex: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 800;
}
</style>
