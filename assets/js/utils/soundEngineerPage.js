/** The sound engineer page's summaries of its service (#1410), kept out of the component so they are tested. */

import { FORMAT_KEYS, SPECIALITY_KEYS, UNIT_KEYS } from '../constants/soundEngineering.js'
import { cheapestPricing } from './teacherPage.js'

/** Mirrors ServiceKind::SoundEngineering, in the order the form offers them. */
export const SPECIALITIES = Object.keys(SPECIALITY_KEYS)
export const DELIVERY_FORMATS = Object.keys(FORMAT_KEYS)
export const OFFER_UNITS = Object.keys(UNIT_KEYS)
/** Mirrors SoundEngineeringDetails::MAX_GEAR. */
export const MAX_GEAR = 30

/**
 * The price the header leads with, as Service::headlineOffer() picks it: the cheapest per track, or the cheapest of any
 * unit without one.
 */
export function headlineOffer(offers) {
  if (!Array.isArray(offers) || offers.length === 0) return null
  const tracks = offers.filter((offer) => offer.unit === 'track')
  return cheapestPricing(tracks.length ? tracks : offers)
}

/**
 * Gear typed as « Reaper, UAD Apollo x8 » or one per line, as the tags the API keeps: trimmed, without empties or
 * repeats, at most MAX_GEAR.
 */
export function parseGear(text) {
  const tags = String(text ?? '')
    .split(/[\n,]/)
    .map((tag) => tag.trim())
    .filter(Boolean)
  return [...new Set(tags)].slice(0, MAX_GEAR)
}
