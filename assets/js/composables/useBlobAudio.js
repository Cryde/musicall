import { computed, onBeforeUnmount, ref, toValue } from 'vue'

/**
 * Plays a sound fetched whole into a blob: the API streams it without byte ranges, which Safari wants before it plays
 * an `<audio>` from a URL. Nothing is fetched before the first play or seek, so a page of sounds loads no audio until
 * one is listened to. Voice notes (#974) and « Avant, après » (#1410).
 *
 * @param {() => Promise<Blob>} fetchBlob
 * @param {import('vue').MaybeRefOrGetter<number>} durationSeconds the measured length, for the progress before loading;
 *   0 or null when nothing measured it, as for a Band Space file (#1431): the sound's own length counts once loaded
 */
export function useBlobAudio(fetchBlob, durationSeconds) {
  const isPlaying = ref(false)
  const isLoading = ref(false)
  const hasFailed = ref(false)
  const currentSeconds = ref(0)
  /** The sound's own length, read once it has loaded. */
  const loadedSeconds = ref(0)
  const totalSeconds = computed(() => toValue(durationSeconds) || loadedSeconds.value)

  const progress = computed(() => {
    const duration = totalSeconds.value
    return duration > 0 ? Math.min(1, currentSeconds.value / duration) : 0
  })

  let audio = null
  let objectUrl = null
  /** A download that lands after the player is gone keeps nothing. */
  let isUnmounted = false
  /** Shared by every caller while the sound downloads, so seeking early cannot fetch it twice. */
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
      console.error('Failed to play the sound:', e)
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
      const blob = await fetchBlob()
      if (isUnmounted) return false
      objectUrl = URL.createObjectURL(blob)
      audio = new Audio(objectUrl)
      audio.addEventListener('play', () => (isPlaying.value = true))
      audio.addEventListener('pause', () => (isPlaying.value = false))
      audio.addEventListener('timeupdate', () => (currentSeconds.value = audio.currentTime))
      audio.addEventListener('loadedmetadata', () => {
        if (Number.isFinite(audio.duration)) loadedSeconds.value = audio.duration
      })
      audio.addEventListener('ended', () => {
        isPlaying.value = false
        currentSeconds.value = 0
      })
      return true
    } catch (e) {
      console.error('Failed to load the sound:', e)
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
    isUnmounted = true
    audio?.pause()
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl)
    }
  })

  return {
    isPlaying,
    isLoading,
    hasFailed,
    currentSeconds,
    totalSeconds,
    progress,
    togglePlayback,
    seek
  }
}
