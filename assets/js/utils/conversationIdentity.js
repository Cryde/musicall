import { displayName } from '../helper/user/displayName.js'

/**
 * A Band Space channel rather than a direct message (#994).
 *
 * Asked of the band space id and not of the participant list, because "has no participants" is also
 * true of a direct message whose only other party closed their account.
 */
export function isChannel(threadMeta) {
  return Boolean(threadMeta?.thread?.band_space_id)
}

/**
 * What to call a conversation in the inbox.
 *
 * Shared rather than written twice because the list and the conversation header disagreed on a thread
 * with no participant, « Utilisateur inconnu » against « Conversation ». A channel has no participant
 * by design, so that disagreement would have become a label members actually read.
 *
 * A channel is named after its band and not after the channel: « Général » in a list of people says
 * nothing about which band it is. The channel name is shown beside it, which is what #1013 needs.
 */
export function conversationTitle(threadMeta, participant = null) {
  if (isChannel(threadMeta)) {
    return threadMeta.thread.band_space_name || 'Groupe'
  }

  return participant ? displayName(participant) : 'Utilisateur inconnu'
}
