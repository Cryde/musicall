/** The lessons page opened from the teacher search (#1315), read from the search store, never from the address. */
import { SERVICE_KINDS } from '../constants/serviceKinds.js'

/** The teacher's place in the loaded results, or null when they are not in them. */
export function searchResultsPosition({ teachers, totalItems, hasMore }, username) {
  const index = teachers.findIndex((teacher) => teacher.username === username)
  if (index === -1) return null

  return {
    position: index + 1,
    total: totalItems,
    current: teachers[index],
    previous: teachers[index - 1] ?? null,
    next: teachers[index + 1] ?? null,
    hasMore
  }
}

/** A teacher's lessons page as the search opens it, on the searched instrument when there is one. */
export function teacherPageLocation(username, instrumentSlug) {
  return {
    name: SERVICE_KINDS.teaching.pageRoute,
    params: { username },
    query: { ...(instrumentSlug ? { instrument: instrumentSlug } : {}), from: 'search' }
  }
}

/** The search's active filters, in the order the results bar shows them. */
export function activeSearchFilters(filters) {
  if (!filters) return []
  return [
    filters.instrument && { key: 'instrument', name: filters.instrument.name },
    filters.city && { key: 'city', city: filters.city.name, radius: filters.radius },
    filters.level && { key: 'level', level: filters.level },
    filters.ageGroup && { key: 'age_group', ageGroup: filters.ageGroup },
    // Only widens a search around a city, as the search itself reads it.
    filters.city && filters.online && { key: 'online' },
    filters.freeTrial && { key: 'free_trial' }
  ].filter(Boolean)
}

/** How far the teacher is from the searched city; null for one found because they teach online. */
export function distanceToSearchedCity(filters, result) {
  return filters?.city && result?.distance != null
    ? { distance: result.distance, city: filters.city.name }
    : null
}

/** Why the teacher is in the results: the city as a distance, or as teaching online, last as the sentence reads. */
export function searchMatches(filters, result) {
  if (!result) return []
  const active = activeSearchFilters(filters)
  const distance = distanceToSearchedCity(filters, result)
  const place = distance
    ? [{ key: 'distance', ...distance }]
    : active.filter((filter) => filter.key === 'online')
  return [...active.filter((filter) => filter.key !== 'city' && filter.key !== 'online'), ...place]
}

/** What « Demander un cours d'essai » can take from the search: its instrument and its level. */
export function requestAnswersFromSearch(filters) {
  return {
    ...(filters?.instrument ? { instrument: filters.instrument.id } : {}),
    ...(filters?.level ? { level: filters.level } : {})
  }
}

/** The page's address to share: how the visitor arrived (`from`) is not part of it. */
export function shareableUrl(fullPath, origin) {
  const url = new URL(fullPath, origin)
  url.searchParams.delete('from')
  return url.href
}
