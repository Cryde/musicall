/**
 * The teacher search (#1314): its filters, as the page holds them, the API asks them and the address keeps them.
 * Pure, so the page only wires them.
 *
 * The filters: `{ instrument, city, radius, level, ageGroup, online, freeTrial, order }`, `instrument` an
 * instrument of the list (`{ id, slug, ... }`), `city` a picked city (`{ name, latitude, longitude }`).
 */

export const TEACHER_RADII = [5, 10, 25, 50]
export const DEFAULT_TEACHER_RADIUS = 10
export const TEACHER_LEVELS = ['beginner', 'intermediate', 'advanced']
export const TEACHER_AGE_GROUPS = ['children', 'teenagers', 'adults', 'seniors']
export const TEACHER_ORDERS = ['distance', 'price', 'free_trial']

export function emptyTeacherFilters() {
  return {
    instrument: null,
    city: null,
    radius: DEFAULT_TEACHER_RADIUS,
    level: null,
    ageGroup: null,
    online: false,
    freeTrial: false,
    order: null
  }
}

/** The order the list is in: none asked, or the nearest without a city, is the nearest with a city, the newest otherwise. */
export function effectiveOrder(order, hasCity) {
  if (!order || order === 'distance') return hasCity ? 'distance' : null
  return order
}

/** What the API is asked for these filters, the default order and page left out. */
export function teacherSearchParams(filters, page = 1) {
  const params = {}
  if (filters.instrument) params.instrument = filters.instrument.id
  if (filters.city) {
    params.lat = filters.city.latitude
    params.lng = filters.city.longitude
    params.radius = filters.radius ?? DEFAULT_TEACHER_RADIUS
    // Only widens a search around a city.
    if (filters.online) params.online = '1'
  }
  if (filters.level) params.level = filters.level
  if (filters.ageGroup) params.age_group = filters.ageGroup
  if (filters.freeTrial) params.free_trial = '1'
  const order = effectiveOrder(filters.order, !!filters.city)
  if (order && order !== 'distance') params.order = order
  if (page > 1) params.page = page
  return params
}

/** The page's address for these filters: what the API is asked, the instrument by its slug and the city named. */
export function teacherSearchUrlQuery(filters) {
  const query = {}
  for (const [key, value] of Object.entries(teacherSearchParams(filters)))
    query[key] = String(value)
  if (filters.instrument) query.instrument = filters.instrument.slug
  if (filters.city) query.location = filters.city.name
  return query
}

const firstOf = (value) => (Array.isArray(value) ? value[0] : value)

/** The filters an address holds, against the instrument list; a value the search does not know is left out. */
export function teacherFiltersFromUrlQuery(query, instruments) {
  const filters = emptyTeacherFilters()
  const slug = firstOf(query.instrument)
  filters.instrument = instruments.find((instrument) => instrument.slug === slug) ?? null

  const latitude = Number.parseFloat(firstOf(query.lat))
  const longitude = Number.parseFloat(firstOf(query.lng))
  const name = firstOf(query.location)
  if (name && Number.isFinite(latitude) && Number.isFinite(longitude)) {
    filters.city = { name, latitude, longitude }
    const radius = Number.parseInt(firstOf(query.radius), 10)
    filters.radius = TEACHER_RADII.includes(radius) ? radius : DEFAULT_TEACHER_RADIUS
    filters.online = firstOf(query.online) === '1'
  }

  const level = firstOf(query.level)
  filters.level = TEACHER_LEVELS.includes(level) ? level : null
  const ageGroup = firstOf(query.age_group)
  filters.ageGroup = TEACHER_AGE_GROUPS.includes(ageGroup) ? ageGroup : null
  filters.freeTrial = firstOf(query.free_trial) === '1'
  const order = firstOf(query.order)
  filters.order = TEACHER_ORDERS.includes(order) ? order : null
  return filters
}

// French elides « de » before a vowel: « d'orgue », but « de basse ». An h is left alone, most instruments
// starting with one aspirate it (« de harpe », « de hautbois »).
const ELIDED = /^[aeiouyàâäéèêëîïôöùûü]/i

/** One instrument as a sentence names it: « de basse », « d'orgue » in French, « bass » in English. */
export function instrumentOf(name, locale) {
  const lower = name.toLocaleLowerCase(locale)
  if (!locale.startsWith('fr')) return lower
  return ELIDED.test(lower) ? `d'${lower}` : `de ${lower}`
}

/** « de basse et de guitare », « bass and guitar ». */
export function instrumentsOf(names, locale) {
  return new Intl.ListFormat(locale, { type: 'conjunction' }).format(
    names.map((name) => instrumentOf(name, locale))
  )
}

/** « 1,1 km », « 24.7 km ». */
export function formatDistance(km, locale) {
  return new Intl.NumberFormat(locale, {
    style: 'unit',
    unit: 'kilometer',
    minimumFractionDigits: 1,
    maximumFractionDigits: 1
  }).format(km)
}
