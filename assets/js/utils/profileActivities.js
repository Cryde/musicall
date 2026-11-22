/** « Ce que je fais » on a member's profile (#1317), kept out of the component so it is tested. */

import { UNIT_KEYS } from '../constants/soundEngineering.js'
import { formatLessonPrice } from './teacherPage.js'

const AVAILABLE_STATUSES = ['looking_for_band', 'available_for_sessions', 'open_to_collaborations']

/** The musician line: what they play first, and whether they are open to playing with others. */
export function musicianActivity(musician) {
  if (!musician) return null
  return {
    instrumentName: musician.instruments?.[0]?.instrument_name ?? null,
    statusLabel: musician.availability_status_label ?? null,
    isAvailable: AVAILABLE_STATUSES.includes(musician.availability_status)
  }
}

/** What the teaching card adds to its service: what they teach, and the trial they offer (prices in cents). */
export function teacherActivity(teacher) {
  if (!teacher) return null
  const trial = teacher.offers_trial ? (teacher.trial_price > 0 ? 'paid' : 'free') : null
  return {
    instrumentNames: (teacher.instruments ?? []).map((instrument) => instrument.instrument_name),
    trial,
    trialPrice: trial === 'paid' ? teacher.trial_price : null
  }
}

/** A service as the profile and its wall line show it (#1415). */
export function serviceCard(service) {
  return {
    id: service.id,
    kind: service.kind,
    headline: service.headline ?? null,
    isDraft: service.status !== 'published',
    price: service.price ?? null,
    priceUnit: service.price_unit ?? null,
    priceQuantity: service.price_quantity ?? null
  }
}

/**
 * The service cards, in the member's order. The API sends drafts to their owner only, and the owner looking at their
 * profile as a visitor does not see them either.
 */
export function serviceCards(services, isOwner) {
  return (services ?? [])
    .filter((service) => isOwner || service.status === 'published')
    .map(serviceCard)
}

// What a teaching price buys; the other units say it as the sound engineer page does.
const PRICE_UNIT_KEYS = {
  ...UNIT_KEYS,
  lesson: 'profile.public.activities.units.lesson',
  package: 'profile.public.activities.units.package'
}

/** What a card's price buys, after « dès 25 € », as an i18n key and its parameters: « les 30 min », « le titre ». */
export function priceUnitLabel(card) {
  if (card.priceUnit === 'lesson') {
    return card.priceQuantity
      ? { key: PRICE_UNIT_KEYS.lesson, params: { minutes: card.priceQuantity } }
      : null
  }
  const key = PRICE_UNIT_KEYS[card.priceUnit]
  return key ? { key, params: {} } : null
}

/** « dès 25€ les 30 min », « dès 80€ le titre », or null without a price; `t` is the page's. */
export function servicePriceText(card, t) {
  if (card.price === null) return null
  const price = t('profile.public.activities.from_price', { price: formatLessonPrice(card.price) })
  const unit = priceUnitLabel(card)
  return unit ? `${price} ${t(unit.key, unit.params)}` : price
}
