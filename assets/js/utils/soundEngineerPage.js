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

/** Where the work happens, for the header: the engineer's studio city, how far they travel, and remote work. */
export function placesSummary(places) {
  const list = Array.isArray(places) ? places : []
  const studio = list.find((place) => place.type === 'at_my_place')
  const travel = list.find((place) => place.type === 'at_client_place')
  return {
    studioCity: studio?.city ?? null,
    city: studio?.city ?? list.find((place) => place.city)?.city ?? null,
    travelRadius: travel ? (travel.radius ?? null) : null,
    travels: Boolean(travel),
    remote: list.some((place) => place.type === 'remote')
  }
}

/** The specialities in the order the kind lists them, whatever order they were ticked in. */
export function orderedSpecialities(specialities) {
  return SPECIALITIES.filter((speciality) => specialities?.includes(speciality))
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

const EMPTY_PLACE = {
  enabled: false,
  city: '',
  country: '',
  latitude: null,
  longitude: null,
  address: '',
  radius: null
}

/** The form's three places from the stored ones: the studio, the travel to the client, and remote work. */
export function placesForm(places) {
  const list = Array.isArray(places) ? places : []
  const fill = (type) => {
    const place = list.find((item) => item.type === type)
    return place
      ? {
          enabled: true,
          city: place.city ?? '',
          country: place.country ?? '',
          latitude: place.latitude ?? null,
          longitude: place.longitude ?? null,
          address: place.address ?? '',
          radius: place.radius ?? null
        }
      : { ...EMPTY_PLACE }
  }
  return {
    studio: fill('at_my_place'),
    client: fill('at_client_place'),
    remote: list.some((item) => item.type === 'remote')
  }
}

/** What the API stores for the form's places: only the ticked ones, each with what its type uses. */
export function placesPayload(form) {
  const located = (place) => ({
    city: place.city || null,
    country: place.country || null,
    latitude: place.latitude ?? null,
    longitude: place.longitude ?? null
  })
  const places = []
  if (form.studio.enabled) {
    places.push({
      type: 'at_my_place',
      ...located(form.studio),
      address: form.studio.address || null
    })
  }
  if (form.client.enabled) {
    places.push({
      type: 'at_client_place',
      ...located(form.client),
      radius: form.client.radius || null
    })
  }
  if (form.remote) places.push({ type: 'remote' })
  return places
}

/**
 * The offers as the API stores them: prices typed in euros, sent in cents; a row without a title is dropped. A missing
 * price stays missing, so the API asks for one rather than storing a free offer.
 */
export function offersPayload(rows) {
  return rows
    .filter((row) => row.title?.trim())
    .map((row) => ({
      unit: row.unit,
      title: row.title.trim(),
      description: row.description?.trim() || null,
      price: row.price == null ? null : Math.round(row.price * 100)
    }))
}
