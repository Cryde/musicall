<template>
  <!-- Deleted from Files, or not playable: the bubble says so rather than offering a dead button. -->
  <span
    v-if="!voiceNote.is_available || hasFailed"
    class="mb-2 last:mb-0 flex items-center gap-2 text-sm italic opacity-80"
  >
    <i class="pi pi-microphone text-xs" aria-hidden="true" />
    Note vocale supprimée
  </span>
  <div v-else class="mb-2 last:mb-0 flex w-64 max-w-full items-center gap-3">
    <button
      type="button"
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-current/15 transition-colors hover:bg-current/25 disabled:opacity-60"
      :aria-label="isPlaying ? 'Mettre en pause la note vocale' : 'Écouter la note vocale'"
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
        aria-label="Position dans la note vocale"
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
import { computed, onBeforeUnmount, ref, useId } from 'vue'
import bandSpaceChatApi from '../../../api/bandSpace/band-space-chat.js'
import { formatVoiceNoteDuration, voiceNoteEnvelopePath } from '../../../utils/chatVoiceNote.js'

const ENVELOPE_WIDTH = 170
const ENVELOPE_HEIGHT = 34

const props = defineProps({
  /** The `voice_note` of one ChatMessage, straight from the API. */
  voiceNote: { type: Object, required: true },
  bandSpaceId: { type: String, required: true }
})

const isPlaying = ref(false)
const isLoading = ref(false)
const hasFailed = ref(false)
const currentSeconds = ref(0)

/** One per player: two notes on a page must not share the clip that shows how far each has played. */
const clipId = `voice-note-clip-${useId()}`
const envelope = computed(() =>
  voiceNoteEnvelopePath(props.voiceNote.peaks ?? [], ENVELOPE_WIDTH, ENVELOPE_HEIGHT)
)
const progress = computed(() =>
  props.voiceNote.duration_seconds > 0
    ? Math.min(1, currentSeconds.value / props.voiceNote.duration_seconds)
    : 0
)

/** Created on the first play only, so a page of notes loads no audio until one is listened to. */
let audio = null
let objectUrl = null
/** Shared by every caller while the note downloads, so seeking early cannot fetch it twice. */
let loading = null

async function togglePlayback() {
  if (isPlaying.value) {
    audio.pause()
    return
  }

  if (!audio && !(await load())) {
    return
  }

  try {
    await audio.play()
  } catch (e) {
    console.error('Failed to play the voice note:', e)
    hasFailed.value = true
  }
}

function load() {
  loading ??= fetchAudio().finally(() => {
    loading = null
  })

  return loading
}

async function fetchAudio() {
  isLoading.value = true
  try {
    const blob = await bandSpaceChatApi.getVoiceNote(props.bandSpaceId, props.voiceNote.file_id)
    objectUrl = URL.createObjectURL(blob)
    audio = new Audio(objectUrl)
    audio.addEventListener('play', () => (isPlaying.value = true))
    audio.addEventListener('pause', () => (isPlaying.value = false))
    audio.addEventListener('timeupdate', () => (currentSeconds.value = audio.currentTime))
    audio.addEventListener('ended', () => {
      isPlaying.value = false
      currentSeconds.value = 0
    })
    return true
  } catch (e) {
    console.error('Failed to load the voice note:', e)
    hasFailed.value = true
    return false
  } finally {
    isLoading.value = false
  }
}

async function seek(seconds) {
  currentSeconds.value = seconds
  if (!audio && !(await load())) {
    return
  }
  audio.currentTime = seconds
}

onBeforeUnmount(() => {
  audio?.pause()
  if (objectUrl) {
    URL.revokeObjectURL(objectUrl)
  }
})
</script>
