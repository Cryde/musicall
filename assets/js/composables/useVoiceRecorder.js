import { computed, getCurrentInstance, onBeforeUnmount, readonly, ref } from 'vue'
import {
  MAX_VOICE_NOTE_SECONDS,
  MIN_VOICE_NOTE_SECONDS,
  pickRecordingMimeType,
  recordingErrorMessage
} from '../utils/chatVoiceNote.js'

const TICK_MS = 250

/**
 * Records a voice note from the microphone (#974).
 *
 * `onLimitReached` is called with the recording when the five minute cap stops it, so the caller can
 * send it as if the member had pressed send. The microphone is released as soon as a recording ends,
 * whichever way it ends, so the browser's « recording » indicator never outlives the note.
 *
 * @param {{onLimitReached: (recording: Blob) => void}} options
 */
export function useVoiceRecorder({ onLimitReached }) {
  const isRecording = ref(false)
  const elapsedSeconds = ref(0)
  const error = ref('')

  const isSupported = computed(
    () =>
      typeof window !== 'undefined' &&
      !!navigator.mediaDevices?.getUserMedia &&
      typeof window.MediaRecorder !== 'undefined' &&
      pickRecordingMimeType((type) => window.MediaRecorder.isTypeSupported(type)) !== null
  )

  let recorder = null
  let stream = null
  let chunks = []
  let startedAt = 0
  let timer = null
  let settle = null

  async function start() {
    if (isRecording.value) {
      return
    }
    error.value = ''

    const mimeType = pickRecordingMimeType((type) => window.MediaRecorder.isTypeSupported(type))
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      recorder = new window.MediaRecorder(stream, { mimeType })
    } catch (e) {
      release()
      error.value = recordingErrorMessage(e)
      return
    }

    chunks = []
    recorder.ondataavailable = (event) => {
      if (event.data.size > 0) chunks.push(event.data)
    }
    recorder.onstop = () => {
      const recording = new Blob(chunks, { type: recorder?.mimeType || mimeType })
      const seconds = (Date.now() - startedAt) / 1000
      release()
      settle?.(seconds >= MIN_VOICE_NOTE_SECONDS ? recording : null)
      settle = null
    }

    recorder.start()
    startedAt = Date.now()
    elapsedSeconds.value = 0
    isRecording.value = true
    timer = setInterval(tick, TICK_MS)
  }

  function tick() {
    elapsedSeconds.value = (Date.now() - startedAt) / 1000
    if (elapsedSeconds.value >= MAX_VOICE_NOTE_SECONDS) {
      // Stopped here, or the next tick would stop it again before the recorder has settled.
      clearInterval(timer)
      timer = null
      stop().then((recording) => recording && onLimitReached(recording))
    }
  }

  /** @returns {Promise<Blob|null>} null for a recording too short to be a note */
  function stop() {
    if (!recorder || recorder.state === 'inactive') {
      return Promise.resolve(null)
    }

    return new Promise((resolve) => {
      settle = resolve
      recorder.stop()
    })
  }

  /**
   * Safe at any point, including while a stop() is still settling: `state` turns inactive as soon as
   * stop() is called but `onstop` fires later, so the pending promise is answered here with null
   * rather than left for ever unanswered.
   */
  function cancel() {
    const pending = settle
    settle = null
    pending?.(null)

    if (recorder && recorder.state !== 'inactive') {
      recorder.onstop = () => release()
      recorder.stop()
    } else if (!pending) {
      release()
    }
  }

  function release() {
    clearInterval(timer)
    timer = null
    for (const track of stream?.getTracks() ?? []) {
      track.stop()
    }
    stream = null
    recorder = null
    isRecording.value = false
  }

  // Outside a component (a unit test) there is nothing to unmount.
  if (getCurrentInstance()) {
    onBeforeUnmount(cancel)
  }

  return {
    isSupported,
    isRecording: readonly(isRecording),
    elapsedSeconds: readonly(elapsedSeconds),
    error,
    start,
    stop,
    cancel
  }
}
