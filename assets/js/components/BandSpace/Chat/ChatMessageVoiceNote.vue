<template>
  <!-- Deleted from Files, or not playable: the bubble says so rather than offering a dead button. -->
  <span
    v-if="!voiceNote.is_available || hasFailed"
    class="mb-2 last:mb-0 flex items-center gap-2 text-sm italic opacity-80"
  >
    <i class="pi pi-microphone text-xs" aria-hidden="true" />
    Note vocale supprimée
  </span>
  <div v-else class="mb-2 last:mb-0 flex w-60 max-w-full items-center gap-3">
    <button
      type="button"
      class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-current/15 transition-colors hover:bg-current/25 disabled:opacity-60"
      :aria-label="isPlaying ? 'Mettre en pause la note vocale' : 'Écouter la note vocale'"
      :disabled="isLoading"
      @click="togglePlayback"
    >
      <i class="pi" :class="isLoading ? 'pi-spin pi-spinner' : isPlaying ? 'pi-pause' : 'pi-play'" aria-hidden="true" />
    </button>
    <div class="flex min-w-0 flex-1 flex-col gap-1">
      <!-- A plain range input: keyboard and screen reader seeking come with it. -->
      <input
        type="range"
        class="w-full cursor-pointer accent-current"
        min="0"
        :max="voiceNote.duration_seconds"
        step="0.1"
        :value="currentSeconds"
        aria-label="Position dans la note vocale"
        @input="seek(Number($event.target.value))"
      />
      <span class="text-[11px] tabular-nums opacity-80">
        {{ formatVoiceNoteDuration(isPlaying || currentSeconds > 0 ? currentSeconds : voiceNote.duration_seconds) }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue'
import bandSpaceChatApi from '../../../api/bandSpace/band-space-chat.js'
import { formatVoiceNoteDuration } from '../../../utils/chatVoiceNote.js'

const props = defineProps({
  /** The `voice_note` of one ChatMessage, straight from the API. */
  voiceNote: { type: Object, required: true },
  bandSpaceId: { type: String, required: true }
})

const isPlaying = ref(false)
const isLoading = ref(false)
const hasFailed = ref(false)
const currentSeconds = ref(0)

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
