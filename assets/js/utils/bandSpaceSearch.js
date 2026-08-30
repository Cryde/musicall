import { BAND_SPACE_ROUTES } from '../constants/bandSpace.js'

/**
 * The record kinds the command palette can return, in the order it groups them. Mirrors
 * App\Enum\BandSpace\BandSpaceSearchResultType: keep the two lists in step.
 *
 * `route` and `param` are the frontend's own business, which is why the API sends a type and a
 * resource id rather than a ready made url: these paths are French and belong to the Vue router,
 * and a rename here must not need a backend deploy.
 */
export const SEARCH_TYPES = Object.freeze([
  {
    type: 'agenda',
    label: 'Agenda',
    icon: 'pi-calendar',
    route: BAND_SPACE_ROUTES.AGENDA,
    param: 'entry'
  },
  {
    type: 'task',
    label: 'Tâches',
    icon: 'pi-check-square',
    route: BAND_SPACE_ROUTES.TASKS,
    param: 'task'
  },
  {
    type: 'note',
    label: 'Notes',
    icon: 'pi-file-edit',
    route: BAND_SPACE_ROUTES.NOTES,
    param: 'note'
  },
  {
    type: 'file',
    label: 'Fichiers',
    icon: 'pi-folder',
    route: BAND_SPACE_ROUTES.FILES,
    param: 'file'
  },
  {
    type: 'setlist',
    label: 'Setlists',
    icon: 'pi-list',
    route: BAND_SPACE_ROUTES.SETLIST,
    param: 'setlist'
  },
  {
    type: 'song',
    label: 'Morceaux',
    icon: 'pi-play',
    route: BAND_SPACE_ROUTES.SETLIST,
    param: 'song'
  },
  {
    type: 'finance',
    label: 'Finances',
    icon: 'pi-wallet',
    route: BAND_SPACE_ROUTES.FINANCE,
    param: 'entry'
  }
])

const TYPE_BY_KEY = new Map(SEARCH_TYPES.map((entry) => [entry.type, entry]))

/**
 * Groups a flat result list by record kind, keeping SEARCH_TYPES order and dropping empty groups.
 * The API already returns them in that order, but the palette must not break if it ever stops.
 *
 * @param {ReadonlyArray<{type: string}>} results
 * @returns {Array<{type: string, label: string, icon: string, results: Array<object>}>}
 */
export function groupResultsByType(results) {
  return SEARCH_TYPES.map(({ type, label, icon }) => ({
    type,
    label,
    icon,
    results: results.filter((result) => result.type === type)
  })).filter((group) => group.results.length > 0)
}

/**
 * The groups flattened back into the order they are rendered in, which is the order the arrow keys
 * move through. Keeping this separate from the grouping is what lets the active row be a single
 * index instead of a group plus an offset.
 *
 * @param {ReadonlyArray<{results: Array<object>}>} groups
 * @returns {Array<object>}
 */
export function flattenGroups(groups) {
  return groups.flatMap((group) => group.results)
}

/**
 * The row an arrow key should move to, wrapping at both ends, and -1 when there is nothing to move
 * through. A bare modulo returns a negative index when stepping up off the first row, which is the
 * whole reason this is a function rather than inline arithmetic.
 *
 * @param {number} length
 * @param {number} current
 * @param {number} step -1 for up, 1 for down
 * @returns {number}
 */
export function moveActiveIndex(length, current, step) {
  if (length <= 0) {
    return -1
  }

  return (current + step + length) % length
}

/**
 * The vue-router location a result opens, or null for a type the palette does not know.
 *
 * @param {{type: string, resource_id: string}} result
 * @param {string} bandSpaceId
 * @returns {{name: string, params: {id: string}, query: Record<string, string>}|null}
 */
export function routeForResult(result, bandSpaceId) {
  const entry = TYPE_BY_KEY.get(result?.type)
  if (!entry) {
    return null
  }

  return {
    name: entry.route,
    params: { id: bandSpaceId },
    query: { [entry.param]: result.resource_id }
  }
}
