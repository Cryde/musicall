/** « Ce que je fais » on a member's profile (#1317), kept out of the component so it is tested. */

import { cheapestPricing } from './teacherPage.js'

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

/** The teacher line: what they teach, their cheapest lesson, and the trial they offer (prices in cents). */
export function teacherActivity(teacher) {
  if (!teacher) return null
  const trial = teacher.offers_trial ? (teacher.trial_price > 0 ? 'paid' : 'free') : null
  return {
    instrumentNames: (teacher.instruments ?? []).map((instrument) => instrument.instrument_name),
    fromPrice: cheapestPricing(teacher.pricing)?.price ?? null,
    trial,
    trialPrice: trial === 'paid' ? teacher.trial_price : null
  }
}
