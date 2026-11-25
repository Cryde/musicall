/** The photographer and videographer pages' summaries of their service (#1411), kept out of the components so they are tested. */

import { VISUAL_SERVICE_KINDS } from '../constants/visualServices.js'

/** A kind's specialities, in the order the form offers them. */
export function kindSpecialities(kind) {
  return Object.keys(VISUAL_SERVICE_KINDS[kind]?.specialityKeys ?? {})
}

const DELIVERY = {
  within: 'profile.visual_service_page.delivery_within',
  daysOnly: 'profile.visual_service_page.delivery_days_only'
}

/**
 * « 40 photos retouchées sous 7 jours », either half alone, or null when neither is said.
 *
 * @param {(key: string, params: object, count: number) => string} t vue-i18n's t, plural by count
 */
export function deliveryLine({ deliverable, delivery_days: days }, t) {
  const what = deliverable?.trim()
  if (what && days) return t(DELIVERY.within, { what, count: days }, days)
  if (days) return t(DELIVERY.daysOnly, { count: days }, days)
  return what || null
}
