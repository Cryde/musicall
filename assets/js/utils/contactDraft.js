/**
 * A message a visitor wrote from « Contacter » on a search card, kept over sign-in as a pending action (#1306) and
 * reopened, addressed to the same member about the same announce, never sent on its own.
 */
export const MESSAGE_SEND = 'message.send'

/** Ids only: the recipient is read again from the announce when the message comes back. */
export function contactDraftPayload({ announceId, recipientId, content }) {
  return { announceId: announceId ?? null, recipientId, content }
}

/** The payload read back from localStorage, or null when it cannot be one. */
export function readContactDraft(payload) {
  if (!payload || typeof payload !== 'object') return null
  const { announceId, recipientId, content } = payload
  if (typeof announceId !== 'string' || typeof recipientId !== 'string') return null
  if (typeof content !== 'string') return null

  return { announceId, recipientId, content }
}

/**
 * Whether the message can still go where it was meant to.
 *
 * @param {{ announce: {author?: {id: string, username: string, deletion_datetime?: string}} | null,
 *   recipientId: string, signedInUsername: string | null }} facts  `announce` null when it is gone
 * @returns {'available' | 'announce_gone' | 'recipient_deleted' | 'own_announce'}
 */
export function contactAvailability({ announce, recipientId, signedInUsername }) {
  const author = announce?.author
  if (!author || author.id !== recipientId) return 'announce_gone'
  if (author.deletion_datetime) return 'recipient_deleted'
  if (author.username === signedInUsername) return 'own_announce'

  return 'available'
}
