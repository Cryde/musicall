<template>
  <div class="flex items-center gap-4 rounded-xl border border-surface-200 dark:border-surface-700 p-3">
    <button
      type="button"
      class="flex w-16 h-16 shrink-0 items-center justify-center rounded-lg text-white transition-opacity hover:opacity-90 disabled:opacity-60"
      :class="audio.side === 'after' ? 'bg-gradient-to-br from-violet-500 to-violet-700' : 'bg-surface-500 dark:bg-surface-600'"
      :aria-label="isPlaying ? t('profile.sound_engineer_page.pause', { title: heading }) : t('profile.sound_engineer_page.play', { title: heading })"
      :disabled="isLoading || hasFailed"
      @click="togglePlayback"
    >
      <i class="pi text-lg" :class="isLoading ? 'pi-spin pi-spinner' : isPlaying ? 'pi-pause' : 'pi-play'" aria-hidden="true" />
    </button>
    <div class="min-w-0 flex-1">
      <p class="font-semibold text-surface-900 dark:text-surface-0 truncate">{{ heading }}</p>
      <p v-if="audio.credit" class="text-xs text-surface-500 dark:text-surface-400 truncate">{{ audio.credit }}</p>
      <p v-if="hasFailed" class="text-xs text-red-700 dark:text-red-400">{{ t('profile.sound_engineer_page.audio_failed') }}</p>
      <!-- The envelope is what is seen, filling as it plays; the range input on top seeks, by pointer and keyboard. -->
      <div
        v-else
        class="relative h-9 mt-1 rounded text-surface-400 dark:text-surface-500 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary"
      >
        <svg class="block h-full w-full" :viewBox="`0 0 ${ENVELOPE_WIDTH} ${ENVELOPE_HEIGHT}`" preserveAspectRatio="none" aria-hidden="true">
          <defs>
            <clipPath :id="clipId">
              <rect :width="ENVELOPE_WIDTH * progress" :height="ENVELOPE_HEIGHT" />
            </clipPath>
          </defs>
          <path :d="envelope" fill="currentColor" opacity="0.5" />
          <path :d="envelope" :clip-path="`url(#${clipId})`" class="fill-violet-600 dark:fill-violet-400" />
        </svg>
        <input
          type="range"
          class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
          min="0"
          :max="audio.duration_seconds"
          step="0.1"
          :value="currentSeconds"
          :aria-label="t('profile.sound_engineer_page.position', { title: heading })"
          :aria-valuetext="formatVoiceNoteDuration(currentSeconds)"
          @input="seek(Number($event.target.value))"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import soundEngineerApi from '../../../api/service/soundEngineer.js'
import { useBlobAudio } from '../../../composables/useBlobAudio.js'
import { SIDE_KEYS } from '../../../constants/soundEngineering.js'
import { formatVoiceNoteDuration, voiceNoteEnvelopePath } from '../../../utils/chatVoiceNote.js'

const ENVELOPE_WIDTH = 240
const ENVELOPE_HEIGHT = 36

/** One half of « Avant, après » (#1410), streamed from the service. */
const props = defineProps({
  serviceId: { type: String, required: true },
  /** One `before_after` item, straight from the API. */
  audio: { type: Object, required: true }
})

const { t } = useI18n()
const { isPlaying, isLoading, hasFailed, currentSeconds, progress, togglePlayback, seek } =
  useBlobAudio(
    () => soundEngineerApi.fetchAudio(props.serviceId, props.audio.side, props.audio.audio_id),
    () => props.audio.duration_seconds
  )

/** « Avant : prise de répète », or the side alone. */
const heading = computed(() => {
  const side = t(SIDE_KEYS[props.audio.side])
  return props.audio.title
    ? t('profile.sound_engineer_page.side_title', { side, title: props.audio.title })
    : side
})
const clipId = `service-audio-clip-${useId()}`
const envelope = computed(() =>
  voiceNoteEnvelopePath(props.audio.peaks ?? [], ENVELOPE_WIDTH, ENVELOPE_HEIGHT)
)
</script>
