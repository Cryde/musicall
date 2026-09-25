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

/** Mirrors ChatVoiceNoteConverter::PEAK_MAX. */
export const PEAK_MAX = 255

/** A pause still shows as a thin line, so the envelope never breaks into separate islands. */
const MIN_PEAK_RATIO = 0.08

/**
 * The closed SVG path of a waveform envelope, mirrored around the middle line, one point per peak.
 * With no peaks (a note older than the measurement) it is a flat line at the minimum height.
 *
 * @param {number[]} peaks 0 to PEAK_MAX, straight from the API
 */
export function voiceNoteEnvelopePath(peaks, width, height) {
  const values = peaks.length > 1 ? peaks : [0, 0]
  const middle = height / 2
  const step = width / (values.length - 1)
  const offsets = values.map(
    (peak) => Math.max(MIN_PEAK_RATIO, Math.min(1, peak / PEAK_MAX)) * middle
  )
  const point = (i, dy) => `${round(i * step)},${round(middle + dy)}`

  const top = offsets.map((offset, i) => point(i, -offset))
  const bottom = offsets.map((offset, i) => point(i, offset)).reverse()

  return `M${[...top, ...bottom].join(' L')} Z`
}

function round(value) {
  return Math.round(value * 10) / 10
}
