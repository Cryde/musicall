export const MIN_CITY_QUERY_LENGTH = 2

/**
 * The city a search should use. A picked suggestion is an object and stands; text typed without
 * picking one used to be dropped silently, so the search ran with no location at all. It now takes
 * the first suggestion, which is what somebody typing « Lyon » and pressing Rechercher meant.
 */
export function resolveTypedCity(value, suggestions) {
  if (typeof value !== 'string' || value.trim().length < MIN_CITY_QUERY_LENGTH) return value
  return suggestions[0] ?? value
}
