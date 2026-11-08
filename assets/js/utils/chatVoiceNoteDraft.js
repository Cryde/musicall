import { recordingExtension } from './chatVoiceNote.js'

/**
 * A chat voice note that could not be sent, kept so it can be resent (#1057). The recorder is gone
 * by then, but the Blob it handed back is not, and that is all a resend needs: the same file, once
 * more, in the same multipart POST.
 */

/**
 * @typedef {Object} VoiceNoteDraft
 * @property {File} file exactly what the failed send uploaded, and what a resend uploads again
 * @property {number} durationSeconds
 * @property {string} playbackUrl an object URL for listening before resending, revoked with the draft
 */

/** The real object URL functions, wrapped so the module can take them injected. */
export function browserObjectUrls() {
  return {
    create: (blob) => URL.createObjectURL(blob),
    revoke: (url) => URL.revokeObjectURL(url)
  }
}

/**
 * Sends voice notes for one composer, and keeps the one that failed until it is resent or discarded.
 *
 * Pure, with the sending and the object URLs injected, so it tests under `node --test`.
 *
 * @param {object} options
 * @param {(voiceNote: File) => Promise<boolean>} options.deliver whether the note went out
 * @param {(draft: VoiceNoteDraft|null) => void} options.onDraftChange
 * @param {{create: (blob: Blob) => string, revoke: (url: string) => void}} [options.urls] injected
 *   for tests
 */
export function createVoiceNoteSender({ deliver, onDraftChange, urls = browserObjectUrls() }) {
  let draft = null
  let isDisposed = false
  let isResending = false

  function replaceDraft(next) {
    if (draft) {
      urls.revoke(draft.playbackUrl)
    }
    draft = next
    onDraftChange(draft)
  }

  /**
   * A new recording, sent by the member or by the five minute limit. Refused while a draft waits: a
   * voice note goes alone, and the one waiting must never be overwritten by the next take.
   *
   * @param {Blob} recording
   * @param {number} durationSeconds
   * @returns {Promise<boolean>} whether it went out
   */
  async function send(recording, durationSeconds) {
    if (draft) {
      return false
    }

    const file = new File([recording], `note-vocale.${recordingExtension(recording.type)}`, {
      type: recording.type
    })
    const wasSent = await deliver(file)
    // A composer unmounted meanwhile has nobody left to resend, nor to revoke the URL.
    if (!wasSent && !isDisposed) {
      replaceDraft({ file, durationSeconds, playbackUrl: urls.create(file) })
    }

    return wasSent
  }

  /**
   * Refused while a resend is already in flight, so a second click never uploads the note twice.
   *
   * @returns {Promise<boolean>} whether it went out; a new failure keeps the draft as it was
   */
  async function resend() {
    if (!draft || isResending) {
      return false
    }

    isResending = true
    try {
      const wasSent = await deliver(draft.file)
      if (wasSent) {
        replaceDraft(null)
      }

      return wasSent
    } finally {
      isResending = false
    }
  }

  /** Ignored while a resend is in flight: the upload may still succeed, or fail and need the draft. */
  function discard() {
    if (!isResending) {
      replaceDraft(null)
    }
  }

  /** For the composer's unmount: the draft goes even mid resend, and a send in flight keeps nothing. */
  function dispose() {
    isDisposed = true
    replaceDraft(null)
  }

  return { send, resend, discard, dispose }
}
