/**
 * The words for what a direct message was sent from (#998), shared by the inbox row, the
 * conversation header and the card above a message so they always say the same thing.
 *
 * Takes the API's `contact_origin` / `latest_contact_origin`, a snapshot that survives the announce
 * being deleted, so nothing here may assume the announce still exists.
 */

import { SERVICE_KINDS } from '../constants/serviceKinds.js'
import { separator } from '../i18n/formatLocale.js'
import { i18n } from '../i18n/index.js'
import { formatDateCompactWithYear } from './date.js'
import { OPTION_LABELS } from './quoteRequest.js'

const ANNOUNCE_LOOKING_FOR_MUSICIAN = 1

// « Demander un cours d'essai » (#1322): what is asked, without and with the instruments.
const LESSON_REQUEST_KEYS = {
  trial: [
    'message.contact_origin.trial_request',
    'message.contact_origin.trial_request_instrument'
  ],
  first: [
    'message.contact_origin.first_lesson_request',
    'message.contact_origin.first_lesson_request_instrument'
  ]
}

/**
 * « Annonce : recherche un batteur », « Annonce : Bassiste cherche un groupe », « Cours : Piano »,
 * « Demande de cours d'essai : basse », « Devis : mixage », « Question : ingénieur du son ».
 */
export function contactOriginLabel(origin) {
  if (!origin) return null
  if (origin.type === 'service') return serviceOriginLabel(origin)

  const instruments = origin.instruments ?? []
  const lessonRequestKeys = LESSON_REQUEST_KEYS[origin.lesson_request]
  if (origin.type === 'teacher_profile' && lessonRequestKeys) {
    const [withoutInstrument, withInstrument] = lessonRequestKeys
    return instruments.length > 0
      ? i18n.global.t(withInstrument, { instrument: instruments.join(', ').toLowerCase() })
      : i18n.global.t(withoutInstrument)
  }
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

/**
 * A service page's message (#1408): what a quote asks for, or the kind a question is about. A need or a kind added
 * after this build still gets a line.
 */
function serviceOriginLabel(origin) {
  const { t } = i18n.global
  if (origin.quote) {
    const need = OPTION_LABELS.need[origin.quote.need]
    return need
      ? t('message.contact_origin.quote_request_need', { need: t(need).toLowerCase() })
      : t('message.contact_origin.quote_request')
  }
  const kind = SERVICE_KINDS[origin.service_kind]
  return kind
    ? t('message.contact_origin.service_question_kind', { kind: t(kind.labelKey).toLowerCase() })
    : t('message.contact_origin.service_question')
}

/**
 * « 5 titres · pour le 15 déc. 2026 · 100 à 300 € », « le 12 déc. 2026 · Lyon »: what a quote request says beyond its
 * need.
 */
function quoteDetails(quote) {
  const { t } = i18n.global
  const parts = []
  if (quote.tracks) parts.push(t('message.contact_origin.quote_tracks', quote.tracks))
  if (quote.deadline) {
    parts.push(
      t('message.contact_origin.quote_deadline', {
        date: formatDateCompactWithYear(quote.deadline)
      })
    )
  }
  if (quote.date) {
    parts.push(
      t('message.contact_origin.quote_date', { date: formatDateCompactWithYear(quote.date) })
    )
  }
  if (quote.city) parts.push(quote.city)
  const budget = OPTION_LABELS.budget[quote.budget]
  if (budget) parts.push(t(budget))
  return parts
}

/** « Rock, Blues · Lyon », or null when the snapshot carries neither. */
export function contactOriginDetails(origin) {
  if (!origin) return null

  const parts = origin.quote ? quoteDetails(origin.quote) : []
  if (origin.styles?.length) parts.push(origin.styles.join(', '))
  if (origin.location_name) parts.push(origin.location_name)

  return parts.length > 0 ? parts.join(` ${separator()} `) : null
}

export function contactOriginIcon(origin) {
  if (origin?.type === 'service')
    return SERVICE_KINDS[origin.service_kind]?.icon ?? 'pi pi-briefcase'
  return origin?.type === 'teacher_profile' ? 'pi pi-book' : 'pi pi-megaphone'
}
