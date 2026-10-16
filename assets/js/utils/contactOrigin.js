/**
 * The words for what a direct message was sent from (#998), shared by the inbox row, the
 * conversation header and the card above a message so they always say the same thing.
 *
 * Takes the API's `contact_origin` / `latest_contact_origin`, a snapshot that survives the announce
 * being deleted, so nothing here may assume the announce still exists.
 */

import { i18n } from '../i18n/index.js'

const ANNOUNCE_LOOKING_FOR_MUSICIAN = 1

/** « Annonce : recherche un batteur », « Annonce : Bassiste cherche un groupe », « Cours : Piano ». */
export function contactOriginLabel(origin) {
  if (!origin) return null

  const instruments = origin.instruments ?? []
  if (origin.type === 'teacher_profile') {
    return instruments.length > 0
      ? i18n.global.t('message.contact_origin.course', { instruments: instruments.join(', ') })
      : i18n.global.t('message.contact_origin.private_lessons')
  }

  const instrument = instruments[0]
  if (!instrument) return i18n.global.t('message.contact_origin.announce')

  return origin.announce_type === ANNOUNCE_LOOKING_FOR_MUSICIAN
    ? i18n.global.t('message.contact_origin.announce_looking_for_musician', {
        instrument: instrument.toLowerCase()
      })
    : i18n.global.t('message.contact_origin.announce_looking_for_band', { instrument })
}

/** « Rock, Blues · Lyon », or null when the snapshot carries neither. */
export function contactOriginDetails(origin) {
  if (!origin) return null

  const parts = []
  if (origin.styles?.length) parts.push(origin.styles.join(', '))
  if (origin.location_name) parts.push(origin.location_name)

  return parts.length > 0 ? parts.join(' · ') : null
}

export function contactOriginIcon(origin) {
  return origin?.type === 'teacher_profile' ? 'pi pi-book' : 'pi pi-megaphone'
}
