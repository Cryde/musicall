import { useI18n } from 'vue-i18n'
import musicianAnnounceApi from '../api/announce/musician.js'
import { displayName } from '../helper/user/displayName.js'
import { useUserSecurityStore } from '../store/user/security.js'
import {
  contactAvailability,
  contactDraftPayload,
  MESSAGE_SEND,
  readContactDraft
} from '../utils/contactDraft.js'
import { usePendingAction, usePendingActionResumer } from './usePendingAction.js'

const UNAVAILABLE_KEYS = {
  announce_gone: 'search.pending.contact_announce_gone',
  recipient_deleted: 'search.pending.contact_recipient_deleted',
  own_announce: 'search.pending.contact_own_announce'
}

/**
 * Keeps the message a visitor wrote to an announce's author when « Envoyer » stops them (#1306).
 *
 * @returns {(draft: { announceId: string|null, recipientId: string, content: string }) => boolean} whether it was kept
 */
export function useKeepContactDraft() {
  const pendingAction = usePendingAction()

  return (draft) =>
    pendingAction.keep({
      kind: MESSAGE_SEND,
      payload: contactDraftPayload(draft),
      stoppedOnSubmit: true
    })
}

/**
 * Offers the kept message back on the calling page after sign-in, once the announce is checked to be still there.
 *
 * @param {(contact: { recipient: object, contactOrigin: object, content: string }) => void} open  opens the message box
 */
export function useContactDraftResumer(open) {
  const { t } = useI18n()
  const userSecurityStore = useUserSecurityStore()

  usePendingActionResumer(MESSAGE_SEND, async (payload) => {
    const draft = readContactDraft(payload)
    const announce = draft ? await announceStillOnline(draft.announceId) : null
    const availability = contactAvailability({
      announce,
      recipientId: draft?.recipientId,
      signedInUsername: userSecurityStore.user?.username ?? null
    })
    if (availability !== 'available') {
      return { unavailable: t(UNAVAILABLE_KEYS[availability]) }
    }

    return {
      label: t('search.pending.contact_label', { name: displayName(announce.author) }),
      resume: () =>
        open({
          recipient: announce.author,
          contactOrigin: { musicianAnnounceId: draft.announceId },
          content: draft.content
        })
    }
  })
}

/** The announce, or null once it is deleted. Any other failure is thrown, so the draft is kept for later. */
async function announceStillOnline(id) {
  try {
    return await musicianAnnounceApi.getItem(id)
  } catch (error) {
    if (error?.response?.status === 404) return null
    throw error
  }
}
