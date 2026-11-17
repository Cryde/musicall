/**
 * The sound engineer search (#1410): its filters, as the page holds them, the API asks them and the address keeps
 * them. Pure, so the page only wires them.
 *
 * The filters: `{ speciality, style, city, radius, online }`, `style` a style of the list (`{ id, slug, ... }`), `city`
 * a picked city (`{ name, latitude, longitude }`).
 */

import { SPECIALITIES } from './soundEngineerPage.js'
import { DEFAULT_TEACHER_RADIUS, TEACHER_RADII } from './teacherSearch.js'

export const SOUND_ENGINEER_RADII = TEACHER_RADII

export function emptySoundEngineerFilters() {
  return {
    speciality: null,
    style: null,
    city: null,
    radius: DEFAULT_TEACHER_RADIUS,
    online: false
  }
}

/** What the API is asked for these filters, the first page left out. */
export function soundEngineerSearchParams(filters, page = 1) {
  const params = {}
  if (filters.speciality) params.speciality = filters.speciality
  if (filters.style) params.style = filters.style.id
  if (filters.city) {
    params.lat = filters.city.latitude
    params.lng = filters.city.longitude
    params.radius = filters.radius ?? DEFAULT_TEACHER_RADIUS
    // Only widens a search around a city.
    if (filters.online) params.online = '1'
  }
  if (page > 1) params.page = page
  return params
}

/** The page's address for these filters: what the API is asked, the style by its slug and the city named. */
export function soundEngineerSearchUrlQuery(filters) {
  const query = {}
  for (const [key, value] of Object.entries(soundEngineerSearchParams(filters)))
    query[key] = String(value)
  if (filters.style) query.style = filters.style.slug
  if (filters.city) query.location = filters.city.name
  return query
}

const firstOf = (value) => (Array.isArray(value) ? value[0] : value)

/** The filters an address holds, against the style list; a value the search does not know is left out. */
export function soundEngineerFiltersFromUrlQuery(query, styles) {
  const filters = emptySoundEngineerFilters()
  const speciality = firstOf(query.speciality)
  filters.speciality = SPECIALITIES.includes(speciality) ? speciality : null
  const slug = firstOf(query.style)
  filters.style = slug ? (styles.find((style) => style.slug === slug) ?? null) : null

  const latitude = Number.parseFloat(firstOf(query.lat))
  const longitude = Number.parseFloat(firstOf(query.lng))
  const name = firstOf(query.location)
  if (name && Number.isFinite(latitude) && Number.isFinite(longitude)) {
    filters.city = { name, latitude, longitude }
    const radius = Number.parseInt(firstOf(query.radius), 10)
    filters.radius = SOUND_ENGINEER_RADII.includes(radius) ? radius : DEFAULT_TEACHER_RADIUS
    filters.online = firstOf(query.online) === '1'
  }
  return filters
}
