/** The teacher page's summaries of the profile data (#1310), kept out of the component so they are tested. */

const LEVEL_ORDER = ['beginner', 'intermediate', 'advanced']
const DURATION_ORDER = ['30min', '1h', '1h30', '2h']

export const DAYS_OF_WEEK = [
  'monday',
  'tuesday',
  'wednesday',
  'thursday',
  'friday',
  'saturday',
  'sunday'
]

/** The cheapest single lesson, the price a visitor compares first. */
export function cheapestPricing(pricing) {
  if (!Array.isArray(pricing) || pricing.length === 0) return null
  return pricing.reduce((cheapest, item) => (item.price < cheapest.price ? item : cheapest))
}

/** The prices from the shortest lesson to the longest, whatever order they were entered in. */
export function pricingByDuration(pricing) {
  if (!Array.isArray(pricing)) return []
  const rank = (item) => {
    const index = DURATION_ORDER.indexOf(item.duration)
    return index === -1 ? DURATION_ORDER.length : index
  }
  return [...pricing].sort((a, b) => rank(a) - rank(b))
}

/** The accepted levels in teaching order, whatever order they were ticked in; unknown values are dropped. */
export function orderedLevels(levels) {
  return LEVEL_ORDER.filter((level) => levels?.includes(level))
}

/** The first and last accepted level in teaching order, for « Débutant à avancé »; one level gives [it, it]. */
export function levelRange(levels) {
  const ordered = orderedLevels(levels)
  if (ordered.length === 0) return null
  return [ordered[0], ordered[ordered.length - 1]]
}

/** Where the lessons happen, for one line: the teacher's own city first, then any place's city. */
export function placesSummary(locations) {
  const places = Array.isArray(locations) ? locations : []
  const own = places.find((place) => place.type === 'teacher_place' && place.city)
  const anyCity = places.find((place) => place.city)
  return {
    city: (own ?? anyCity)?.city ?? null,
    atTeacher: places.some((place) => place.type === 'teacher_place'),
    atStudent: places.some((place) => place.type === 'student_place'),
    online: places.some((place) => place.type === 'online')
  }
}

/** The week from Monday, each day with its slots in time order, for the 7-day grid. */
export function availabilityByDay(slots) {
  const list = Array.isArray(slots) ? slots : []
  return DAYS_OF_WEEK.map((day) => ({
    day,
    slots: list
      .filter((slot) => slot.day_of_week === day)
      .map((slot) => ({ start: slot.start_time, end: slot.end_time }))
      .sort((a, b) => a.start.localeCompare(b.start))
  }))
}
