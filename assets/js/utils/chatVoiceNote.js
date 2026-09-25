/**
 * The rules around a chat voice note that do not need a browser to be checked (#974).
 */

/** Mirrors ChatVoiceNoteConverter::MAX_DURATION_SECONDS: recording stops by itself there. */
export const MAX_VOICE_NOTE_SECONDS = 300

/** Shorter than this is a slip of the finger, not a note: it is dropped rather than sent. */
export const MIN_VOICE_NOTE_SECONDS = 1

/**
 * In order of preference. Chrome and Firefox record WebM, Safari only MP4, older Firefox Ogg. The
 * server converts all of them to AAC, so this only has to find one the browser can produce.
 */
const RECORDING_MIME_TYPES = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus']

export const MICROPHONE_DENIED_MESSAGE =
  "L'accès au micro a été refusé. Autorisez-le dans les réglages du navigateur pour enregistrer une note vocale."
export const NO_MICROPHONE_MESSAGE = 'Aucun micro détecté.'
export const RECORDING_FAILED_MESSAGE = "L'enregistrement n'a pas pu démarrer."

/**
 * @param {(mimeType: string) => boolean} isTypeSupported MediaRecorder.isTypeSupported
 * @returns {string|null} null when the browser records none of them
 */
export function pickRecordingMimeType(isTypeSupported) {
  return RECORDING_MIME_TYPES.find((mimeType) => isTypeSupported(mimeType)) ?? null
}

/** The extension the upload is named with, for the Files module's sake only: the server sniffs. */
export function recordingExtension(mimeType) {
  if (mimeType.startsWith('audio/mp4')) return 'm4a'
  if (mimeType.startsWith('audio/ogg')) return 'ogg'
  return 'webm'
}

/** « 0:07 », « 4:59 », for the recording timer and the player alike. */
export function formatVoiceNoteDuration(totalSeconds) {
  const seconds = Math.max(0, Math.floor(totalSeconds))

  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

/**
 * What to tell a member whose recording could not start, from the DOMException getUserMedia threw.
 *
 * @param {{name?: string}} error
 */
export function recordingErrorMessage(error) {
  if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
    return MICROPHONE_DENIED_MESSAGE
  }
  if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
    return NO_MICROPHONE_MESSAGE
  }

  return RECORDING_FAILED_MESSAGE
}
