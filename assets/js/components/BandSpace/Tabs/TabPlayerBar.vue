<template>
  <div class="border-t border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 shrink-0">
    <!-- The song's form as the progress line: one block per section, sized by its bars. -->
    <div class="px-4 pt-2 max-md:px-2.5 max-md:pt-1.5">
      <div class="relative flex gap-0.5" role="group" :aria-label="t('band_space_tabs.player.timeline')">
        <button
          v-for="(section, k) in player.sections"
          :key="k"
          type="button"
          class="tab-segment tab-section-hue"
          :class="k === player.loopedSection ? 'tab-segment-looped' : ''"
          :style="{ flexGrow: section.bars, '--hue': sectionHue(section.letter) }"
          :aria-current="k === player.currentSection ? 'step' : undefined"
          :aria-label="t('band_space_tabs.section.go', { name: sectionName(section) })"
          :title="titleOf(section)"
          @click="player.goToSection(k)"
          @dblclick="player.loopSection(k)"
        >
          <b>{{ section.letter || '·' }}</b>
          <span class="truncate max-lg:hidden">{{ sectionName(section) }}</span>
        </button>
        <span
          v-if="player.barCount"
          class="tab-playhead"
          :style="{ left: `${((player.bar + 0.5) / player.barCount) * 100}%` }"
          aria-hidden="true"
        />
      </div>
    </div>

    <div class="flex items-center gap-2.5 px-4 py-2.5 max-md:gap-2 max-md:px-2.5 max-md:py-2">
      <div class="flex items-center gap-1.5 shrink-0">
        <span class="max-md:hidden">
          <Button
            icon="pi pi-step-backward"
            severity="secondary"
            text
            rounded
            :aria-label="t('band_space_tabs.player.restart')"
            @click="player.restart()"
          />
        </span>
        <Button
          :icon="player.playing ? 'pi pi-pause' : 'pi pi-play'"
          rounded
          class="w-12! h-12!"
          :loading="!player.soundReady && !player.soundFailed"
          :disabled="!player.soundReady"
          :aria-label="t('band_space_tabs.player.play')"
          @click="player.playPause()"
        />
        <span class="max-md:hidden">
          <Button
            icon="pi pi-stop"
            severity="secondary"
            text
            rounded
            :aria-label="t('band_space_tabs.player.stop')"
            @click="player.stop()"
          />
        </span>
      </div>

      <div class="flex flex-col min-w-0 leading-tight md:min-w-36 max-md:flex-1">
        <b class="text-sm tabular-nums">
          {{ formatDuration(player.time) }} / {{ formatDuration(player.duration) }}
          <span class="md:hidden">· {{ speedPercent(player.speed) }}</span>
        </b>
        <span class="text-xs text-surface-500 dark:text-surface-400 truncate">
          <span class="max-md:hidden">{{ currentName }} · </span>
          {{ t('band_space_tabs.section.bar', { bar: player.bar + 1 }) }}<span class="max-md:hidden"> / {{ player.barCount }}</span>
          <span class="md:hidden">
            · <QuarterNote /> = {{ player.heardTempo }} · {{ currentName }}
          </span>
        </span>
        <span v-if="!player.soundReady" class="text-xs text-surface-500 dark:text-surface-400" aria-live="polite">
          {{
            player.soundFailed
              ? t('band_space_tabs.player.sounds_failed')
              : t('band_space_tabs.player.sounds_loading', { percent: speedPercent(player.soundProgress) })
          }}
          <button
            v-if="player.soundFailed"
            type="button"
            class="border-0 bg-transparent p-0 text-xs font-semibold text-primary underline cursor-pointer"
            @click="player.retrySounds()"
          >
            {{ t('band_space_tabs.player.sounds_retry') }}
          </button>
        </span>
      </div>

      <div
        class="flex flex-col leading-tight text-sm whitespace-nowrap pl-3 border-l border-surface-200 dark:border-surface-700 max-md:hidden"
        :title="t('band_space_tabs.player.tempo_hint')"
      >
        <b class="tabular-nums" :class="player.speed < 1 ? 'text-primary' : ''">
          <span class="sr-only">{{ t('band_space_tabs.player.tempo') }}</span>
          <QuarterNote /> = {{ player.heardTempo }}
        </b>
        <span class="text-xs text-surface-500 dark:text-surface-400">
          <template v-if="player.speed < 1">{{ t('band_space_tabs.player.written', { tempo: player.writtenTempo }) }} · </template>{{ player.timeSignature }}
        </span>
      </div>

      <span
        v-if="player.range"
        class="inline-flex items-center gap-1.5 pl-2.5 pr-0.5 py-0.5 rounded-full bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-200 text-sm font-semibold whitespace-nowrap max-md:hidden"
      >
        <i class="pi pi-replay text-xs" aria-hidden="true" />
        {{ loopLabel }}
        <Button
          icon="pi pi-times"
          text
          rounded
          size="small"
          class="w-7! h-7! text-inherit!"
          :aria-label="t('band_space_tabs.player.clear_loop')"
          @click="player.clearRange()"
        />
      </span>

      <div class="flex-1 max-md:hidden" />

      <span class="max-md:hidden">
        <Button
          severity="secondary"
          :outlined="player.speed >= 1"
          :aria-label="t('band_space_tabs.speed.label')"
          :title="t('band_space_tabs.speed.label')"
          :aria-expanded="speedOpen"
          aria-haspopup="dialog"
          @click="speedPopover.toggle($event)"
        >
          <i class="pi pi-gauge" aria-hidden="true" />
          <b class="tabular-nums">{{ speedPercent(player.speed) }}</b>
          <i v-if="player.trainer" class="pi pi-angle-double-up" :title="t('band_space_tabs.speed.trainer_hint')" aria-hidden="true" />
        </Button>
      </span>
      <Popover ref="speedPopover" @show="speedOpen = true" @hide="speedOpen = false">
        <TabSpeedPanel
          class="w-72"
          :speed="player.speed"
          :trainer="player.trainer"
          @update:speed="player.setSpeed($event)"
          @toggle-trainer="player.toggleTrainer()"
        />
      </Popover>

      <span v-for="toggle in toggles" :key="toggle.key" class="max-md:hidden">
        <Button
          severity="secondary"
          :outlined="!toggle.on"
          :aria-pressed="toggle.on"
          :aria-label="toggle.label"
          :title="toggle.label"
          @click="toggle.action()"
        >
          <MetronomeIcon v-if="toggle.key === 'metronome'" />
          <i v-else :class="toggle.icon" aria-hidden="true" />
          <span class="max-[90rem]:hidden">{{ toggle.label }}</span>
        </Button>
      </span>

      <span class="md:hidden">
        <Button
          :label="t('band_space_tabs.player.loop_section')"
          icon="pi pi-replay"
          rounded
          size="small"
          :outlined="player.loopedSection < 0"
          :aria-pressed="player.loopedSection >= 0"
          :disabled="player.sections.length === 0"
          @click="loopCurrentSection"
        />
      </span>
      <span class="md:hidden">
        <Button
          icon="pi pi-ellipsis-h"
          severity="secondary"
          outlined
          :aria-label="t('band_space_tabs.options.title')"
          @click="emit('options')"
        />
      </span>
    </div>

    <p v-if="player.looping && !player.range" class="m-0 -mt-1 px-4 pb-2.5 text-xs text-surface-500 dark:text-surface-400">
      {{ t('band_space_tabs.player.loop_hint') }}
    </p>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTabLabels } from '../../../composables/useTabLabels.js'
import { formatDuration } from '../../../utils/setlistDuration.js'
import { sectionHue } from '../../../utils/tabSections.js'
import MetronomeIcon from '../../Tabs/MetronomeIcon.vue'
import QuarterNote from '../../Tabs/QuarterNote.vue'
import TabSpeedPanel from './TabSpeedPanel.vue'
import './tabSectionHue.css'

/**
 * The bar under the score: the song's sections as the progress line, the transport, where the band
 * is, the tempo (for information), the loop, the speed and the toggles. `player` is the reader's
 * useTabPlayer, made reactive: this bar shows its state and calls its actions, like a store.
 */
const props = defineProps({
  player: { type: Object, required: true }
})
const emit = defineEmits(['options'])
const { t } = useI18n()
const { sectionName, sectionBars, speedPercent } = useTabLabels()

const speedPopover = ref(null)
const speedOpen = ref(false)

function titleOf(section) {
  return `${sectionName(section)}, ${sectionBars(section)}`
}

const currentName = computed(() => {
  const section = props.player.sections[props.player.currentSection]
  return section ? sectionName(section) : ''
})

const loopLabel = computed(() => {
  const player = props.player
  const section = player.sections[player.loopedSection]
  if (section) return t('band_space_tabs.player.loop_named', { name: sectionName(section) })
  const bars = player.rangeBars
  if (!bars) return ''
  return bars.first === bars.last
    ? t('band_space_tabs.player.loop_bar', { bar: bars.first + 1 })
    : t('band_space_tabs.player.loop_bars', { start: bars.first + 1, end: bars.last + 1 })
})

const toggles = computed(() => [
  {
    key: 'loop',
    icon: 'pi pi-replay',
    label: t('band_space_tabs.player.loop'),
    on: props.player.looping,
    action: () => props.player.setLooping(!props.player.looping)
  },
  {
    key: 'metronome',
    label: t('band_space_tabs.player.metronome'),
    on: props.player.metronome,
    action: () => props.player.toggleMetronome()
  },
  {
    key: 'count-in',
    icon: 'pi pi-stopwatch',
    label: t('band_space_tabs.player.count_in'),
    on: props.player.countIn,
    action: () => props.player.toggleCountIn()
  }
])

/** The phone's « Boucler »: loops the section playing, or lets go of the loop. */
function loopCurrentSection() {
  const player = props.player
  if (player.loopedSection >= 0) player.clearRange()
  else player.loopSection(player.currentSection)
}
</script>

<style>
.tab-segment {
  flex-basis: 0;
  flex-shrink: 1;
  min-width: 0;
  height: 1.625rem;
  border: 0;
  border-radius: 0.25rem;
  padding: 0 0.375rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
  overflow: hidden;
  white-space: nowrap;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}
.tab-segment b {
  font-size: 0.65rem;
  font-weight: 800;
  opacity: 0.7;
}
.tab-segment:hover {
  filter: brightness(0.96);
}
.tab-segment[aria-current] {
  box-shadow: inset 0 0 0 2px hsl(var(--hue) 45% 42%);
}
.dark-mode .tab-segment[aria-current] {
  box-shadow: inset 0 0 0 2px hsl(var(--hue) 60% 65%);
}
.tab-segment.tab-segment-looped {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
}
@media (width < 64rem) {
  .tab-segment {
    justify-content: center;
    padding: 0 0.125rem;
  }
}
@media (width < 48rem) {
  .tab-segment {
    height: 1.375rem;
    font-size: 0.7rem;
  }
}
/* Where the band is in the song. */
.tab-playhead {
  position: absolute;
  top: -0.25rem;
  bottom: -0.25rem;
  width: 3px;
  margin-left: -1px;
  border-radius: 3px;
  background: var(--p-red-500);
  pointer-events: none;
  transition: left 0.2s linear;
}
</style>
