import { i18n } from '../i18n/index.js'

/**
 * The chat composer's pending attachment list: the Band Space objects a message will point at, held
 * between the moment they are picked and the moment the message goes out (#971).
 *
 * Kept as plain functions over a plain array rather than inside the composer, because there is more
 * than one way in. The picker is the first; #972 adds a second by resolving a pasted Band Space URL,
 * and it has to answer a duplicate and a full list exactly the same way.
 */

/**
 * Mirrors ChatMessageCreate::MAX_ATTACHMENTS. Enforced here so the sixth pick is refused with a
 * sentence instead of being sent and answered with a 422.
 */
export const MAX_CHAT_ATTACHMENTS = 5

/** The refusal shown once the draft holds MAX_CHAT_ATTACHMENTS, by the composer and the picker alike. */
export function fullDraftMessage() {
  return i18n.global.t('band_space_chat.draft.full', { max: MAX_CHAT_ATTACHMENTS })
}

/**
 * @typedef {Object} DraftAttachment
 * @property {string} id the synthetic `<type>-<uuid>` identifier, which is what goes on the wire
 * @property {string} type a BandSpaceSearchResultType value, for the chip's icon
 * @property {string} title what the chip reads
 */

/**
 * The draft with one more object in it, or the draft untouched and the reason why.
 *
 * Both refusals are the client half of a server rule: the cap is a 422 and a repeated reference is
 * a 422 too, since ChatMessageCreate refuses a duplicate rather than collapsing it. Catching them
 * here is what keeps those two 422s meaning « the client has a bug ».
 *
 * @param {ReadonlyArray<DraftAttachment>} draft
 * @param {{id?: string, type?: string, title?: string}} candidate a BandSpaceSearchResult, or
 *   anything else carrying the same three fields
 * @returns {{attachments: DraftAttachment[], error: string|null}}
 */
export function addToDraft(draft, candidate) {
  const attachments = [...draft]

  if (!candidate?.id || !candidate.type) {
    return { attachments, error: i18n.global.t('band_space_chat.draft.unusable') }
  }

  if (attachments.some((attachment) => attachment.id === candidate.id)) {
    return { attachments, error: i18n.global.t('band_space_chat.draft.duplicate') }
  }

  if (attachments.length >= MAX_CHAT_ATTACHMENTS) {
    return { attachments, error: fullDraftMessage() }
  }

  attachments.push({ id: candidate.id, type: candidate.type, title: candidate.title ?? '' })

  return { attachments, error: null }
}

/**
 * @param {ReadonlyArray<DraftAttachment>} draft
 * @param {string} id
 * @returns {DraftAttachment[]}
 */
export function removeFromDraft(draft, id) {
  return draft.filter((attachment) => attachment.id !== id)
}

/**
 * What the draft holds after a send attempt.
 *
 * Emptied when the message went out, and kept exactly as it was when it did not. The composer
 * deliberately keeps the text on a failure so the member can retry, and references picked one by one
 * are worth more than the text: losing them would mean reopening the picker and searching for each
 * of them again.
 *
 * @param {ReadonlyArray<DraftAttachment>} draft
 * @param {boolean} wasSent
 * @returns {DraftAttachment[]}
 */
export function draftAfterSend(draft, wasSent) {
  return wasSent ? [] : [...draft]
}

/**
 * The identifiers the POST carries, which are the synthetic ones the search endpoint handed out in
 * the first place: no translation, which is why the picker can write what the palette reads.
 *
 * @param {ReadonlyArray<DraftAttachment>} draft
 * @returns {string[]}
 */
export function draftIdentifiers(draft) {
  return draft.map((attachment) => attachment.id)
}

/**
 * Whether the composer has anything to send: some text, or at least one attachment, since a message
 * may carry references alone. The same rule ChatMessageCreate applies to `content`.
 *
 * @param {string} content the composer's text, in the stored format
 * @param {ReadonlyArray<DraftAttachment>} draft
 * @returns {boolean}
 */
export function canSendDraft(content, draft) {
  return content.trim() !== '' || draft.length > 0
}
