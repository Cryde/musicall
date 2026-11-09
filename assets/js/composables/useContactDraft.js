import { until } from '@vueuse/core'
import { useI18n } from 'vue-i18n'
import musicianAnnounceApi from '../api/announce/musician.js'
import { displayName } from '../helper/user/displayName.js'
import { useUserSecurityStore } from '../store/user/security.js'
import {
  contactAvailability,
  contactDraftPayload,
  MESSAGE_SEND,
  profileContactAvailability,
  readContactDraft
} from '../utils/contactDraft.js'
import { usePendingAction, usePendingActionResumer } from './usePendingAction.js'

const UNAVAILABLE_KEYS = {
  announce_gone: 'search.pending.contact_announce_gone',
  recipient_deleted: 'search.pending.contact_recipient_deleted',
  own_announce: 'search.pending.contact_own_announce'
}
const PROFILE_UNAVAILABLE_KEYS = {
  profile_gone: 'profile.common.pending_contact_profile_gone',
  recipient_deleted: 'search.pending.contact_recipient_deleted',
  own_profile: 'profile.common.pending_contact_own_profile',
  announce_gone: 'search.pending.contact_announce_gone'
}
// A profile still loading after this is a hung request: the draft waits for the next visit.
const PROFILE_LOAD_TIMEOUT_MS = 10_000

/**
 * Keeps the message a visitor wrote to an announce's author (#1306) or from a profile (#1305) when « Envoyer » stops
 * them. `announceId` is null for a message about no announce.
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
    const announce = draft?.announceId ? await announceStillOnline(draft.announceId) : null
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

/**
 * Keeps a message a visitor wrote on a profile (#1305).
 *
 * @returns {(draft: { profile: object, announceId?: string|null, content: string }) => string} the sentence of the
 *   sign-in prompt, promising the message is kept only when it was
 */
export function useKeepProfileContactDraft() {
  const { t } = useI18n()
  const keepContactDraft = useKeepContactDraft()

  return ({ profile, announceId = null, content }) =>
    keepContactDraft({ announceId, recipientId: profile.user_id, content })
      ? t('search.pending.contact_sign_in', { name: displayName(profile) })
      : t('profile.common.contact_auth_required')
}

/**
 * Offers the kept message back on a profile page after sign-in, checked against the profile the page loaded.
 *
 * @param {{ isLoading: import('vue').Ref<boolean>, loadedProfile: () => object|null }} page  `loadedProfile` is
 *   the profile once loaded, null when it could not be
 * @param {(contact: { contactOrigin: object|null, content: string }) => void} open  opens the message box
 */
export function useProfileContactDraftResumer({ isLoading, loadedProfile }, open) {
  const { t } = useI18n()
  const userSecurityStore = useUserSecurityStore()

  usePendingActionResumer(MESSAGE_SEND, async (payload) => {
    const draft = readContactDraft(payload)
    if (draft) {
      await until(isLoading).toBe(false, {
        timeout: PROFILE_LOAD_TIMEOUT_MS,
        throwOnTimeout: false
      })
      // Thrown, so the draft is kept rather than dropped as unavailable.
      if (isLoading.value) throw new Error('The profile is still loading.')
    }
    const profile = draft ? loadedProfile() : null
    const availability = profileContactAvailability({
      profile,
      recipientId: draft?.recipientId,
      announceId: draft?.announceId ?? null,
      signedInUsername: userSecurityStore.user?.username ?? null
    })
    if (availability !== 'available') {
      return { unavailable: t(PROFILE_UNAVAILABLE_KEYS[availability]) }
    }

    return {
      label: t('search.pending.contact_label', { name: displayName(profile) }),
      resume: () =>
        open({
          contactOrigin: draft.announceId ? { musicianAnnounceId: draft.announceId } : null,
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
