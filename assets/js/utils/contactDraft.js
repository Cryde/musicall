/**
 * A message a visitor wrote from « Contacter » on a search card (#1306) or a profile (#1305), kept over sign-in as a
 * pending action and reopened, addressed to the same member about the same announce if any, never sent on its own.
 */
export const MESSAGE_SEND = 'message.send'

/** Ids only: the recipient is read again from the announce or the profile when the message comes back. */
export function contactDraftPayload({ announceId, recipientId, content }) {
  return { announceId: announceId ?? null, recipientId, content }
}

/** The payload read back from localStorage, or null when it cannot be one. `announceId` is null from a profile. */
export function readContactDraft(payload) {
  if (!payload || typeof payload !== 'object') return null
  const { announceId, recipientId, content } = payload
  if (announceId !== null && typeof announceId !== 'string') return null
  if (typeof recipientId !== 'string') return null
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

/**
 * Whether a message written on a profile can still go to that member, about the announce it was written from.
 *
 * @param {{ profile: {user_id: string, username: string, deletion_datetime?: string,
 *   musician_announces?: Array<{id: string}>} | null, recipientId: string, announceId: string | null,
 *   signedInUsername: string | null }} facts  `profile` as the page loaded it, null when it is gone
 * @returns {'available' | 'profile_gone' | 'recipient_deleted' | 'own_profile' | 'announce_gone'}
 */
export function profileContactAvailability({ profile, recipientId, announceId, signedInUsername }) {
  if (!profile || profile.user_id !== recipientId) return 'profile_gone'
  if (profile.deletion_datetime) return 'recipient_deleted'
  if (profile.username === signedInUsername) return 'own_profile'
  const announces = profile.musician_announces ?? []
  if (announceId !== null && !announces.some((announce) => announce.id === announceId)) {
    return 'announce_gone'
  }

  return 'available'
}
