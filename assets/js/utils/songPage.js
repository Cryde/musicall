import { BAND_SPACE_ROUTES } from '../constants/bandSpace.js'

/**
 * A song's own page. A Setlists screen other than the répertoire passes its own name, which the page's
 * way back shows (see songPageWayBack).
 *
 * @param {string} bandSpaceId
 * @param {string} songId
 * @param {string} [backLabel]
 */
export function songPageRoute(bandSpaceId, songId, backLabel = '') {
  const route = { name: BAND_SPACE_ROUTES.SONG, params: { id: bandSpaceId, songId } }
  return backLabel ? { ...route, state: { backLabel } } : route
}

/**
 * The song page a Setlists location still pointing at the drawer (`?song=`) stands for, or null. Every
 * link to a song was built that way before the page existed, and some sit in messages already sent.
 *
 * @param {{params: {id?: string}, query: Record<string, unknown>}} to
 * @returns {object|null}
 */
export function songPageRedirect(to) {
  const songId = to.query.song
  if (typeof songId !== 'string' || songId === '' || typeof to.params.id !== 'string') return null
  return { ...songPageRoute(to.params.id, songId), replace: true }
}

/**
 * Where a song page's way back leads. From the Setlists page it is a step back in history, so that page
 * comes back as it was left (the répertoire with its search and scroll, a set, the lyrics), under the
 * name the screen that opened the song gave. From anywhere else it opens the répertoire.
 *
 * @param {{back?: unknown, backLabel?: unknown}|null} historyState the song page's `history.state`
 * @param {(path: string) => unknown} routeNameOf the route name a path resolves to
 * @returns {{returns: boolean, label: string}} label is empty for the répertoire
 */
export function songPageWayBack(historyState, routeNameOf) {
  const { back, backLabel } = historyState ?? {}
  const returns = typeof back === 'string' && routeNameOf(back) === BAND_SPACE_ROUTES.SETLIST
  return { returns, label: returns && typeof backLabel === 'string' ? backLabel : '' }
}
