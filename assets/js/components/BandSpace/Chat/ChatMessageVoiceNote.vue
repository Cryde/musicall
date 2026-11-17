<template>
  <!-- Deleted from Files, or not playable: the bubble says so rather than offering a dead button. -->
  <span
    v-if="!voiceNote.is_available || hasFailed"
    class="mb-2 last:mb-0 flex items-center gap-2 text-sm italic opacity-80"
  >
    <i class="pi pi-microphone text-xs" aria-hidden="true" />
    {{ t('band_space_chat.voice_note.deleted') }}
  </span>
  <div v-else class="mb-2 last:mb-0 flex w-64 max-w-full items-center gap-3">
    <button
      type="button"
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-current/15 transition-colors hover:bg-current/25 disabled:opacity-60"
      :aria-label="isPlaying ? t('band_space_chat.voice_note.pause') : t('band_space_chat.voice_note.play')"
      :disabled="isLoading"
      @click="togglePlayback"
    >
      <i class="pi" :class="isLoading ? 'pi-spin pi-spinner' : isPlaying ? 'pi-pause' : 'pi-play'" aria-hidden="true" />
    </button>
    <!-- The envelope is what is seen, filling as the note plays (#974). The range input lies on top of
         it, invisible, so pointer and keyboard seeking and the screen reader all come with it. -->
    <div class="relative h-9 min-w-0 flex-1 rounded has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-current">
      <svg
        class="block h-full w-full"
        :viewBox="`0 0 ${ENVELOPE_WIDTH} ${ENVELOPE_HEIGHT}`"
        preserveAspectRatio="none"
        aria-hidden="true"
      >
        <defs>
          <clipPath :id="clipId">
            <rect :width="ENVELOPE_WIDTH * progress" :height="ENVELOPE_HEIGHT" />
          </clipPath>
        </defs>
        <path :d="envelope" fill="currentColor" opacity="0.35" />
        <path :d="envelope" fill="currentColor" :clip-path="`url(#${clipId})`" />
      </svg>
      <input
        type="range"
        class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
        min="0"
        :max="voiceNote.duration_seconds"
        step="0.1"
        :value="currentSeconds"
        :aria-label="t('band_space_chat.voice_note.position')"
        :aria-valuetext="formatVoiceNoteDuration(currentSeconds)"
        @input="seek(Number($event.target.value))"
      />
    </div>
    <span class="shrink-0 text-xs tabular-nums opacity-80">
      {{ formatVoiceNoteDuration(isPlaying || currentSeconds > 0 ? currentSeconds : voiceNote.duration_seconds) }}
    </span>
  </div>
</template>

<script setup>
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBlobAudio } from '../../../composables/useBlobAudio.js'
import { formatVoiceNoteDuration, voiceNoteEnvelopePath } from '../../../utils/chatVoiceNote.js'

const ENVELOPE_WIDTH = 170
const ENVELOPE_HEIGHT = 34

const props = defineProps({
  /** The `voice_note` of one ChatMessage, straight from the API. */
  voiceNote: { type: Object, required: true },
  /** Fetches the recording as a blob, from whichever conversation owns it. */
  fetchBlob: { type: Function, required: true }
})

const { t } = useI18n()
const { isPlaying, isLoading, hasFailed, currentSeconds, progress, togglePlayback, seek } =
  useBlobAudio(
    () => props.fetchBlob(props.voiceNote.file_id),
    () => props.voiceNote.duration_seconds
  )

/** One per player: two notes on a page must not share the clip that shows how far each has played. */
const clipId = `voice-note-clip-${useId()}`
const envelope = computed(() =>
  voiceNoteEnvelopePath(props.voiceNote.peaks ?? [], ENVELOPE_WIDTH, ENVELOPE_HEIGHT)
)
</script>
