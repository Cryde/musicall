import { visibleServiceSearches } from './serviceSearchLinks.js'

/**
 * The annuaire's cards (#1414), as `{ key, kind, count }`: one per kind whose search the viewer may see, in the kinds
 * order, with how many services that search lists (0 for a kind the API did not count).
 */
export function directoryCards(kinds, counts, isTester) {
  const countOf = new Map(counts.map(({ kind, count }) => [kind, count]))

  return visibleServiceSearches(kinds, isTester).map(([key, kind]) => ({
    key,
    kind,
    count: countOf.get(key) ?? 0
  }))
}
