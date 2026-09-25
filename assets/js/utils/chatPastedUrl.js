import { SEARCH_TYPES } from './bandSpaceSearch.js'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

/**
 * The attachment identifier a pasted Band Space URL names, or null when the paste is anything else
 * (#972).
 *
 * The inverse of routeForResult(): the palette builds `{route, ?param=id}` links from SEARCH_TYPES,
 * so a URL copied from one of them is matched against the same table. The path is resolved by the
 * Vue router rather than by a pattern of our own, which is what keeps the French slugs (`taches`,
 * `finances`) out of this file. Only a URL of this site and of the space being written in counts:
 * a link to another band stays text. The server has the last word either way.
 *
 * @param {string} text what was pasted
 * @param {{origin: string, bandSpaceId: string, resolve: (path: string) => {name?: string, params: object, query: object}}} context
 * @returns {string|null} `<type>-<uuid>`, what a chat message's `attachments` takes
 */
export function attachmentIdentifierForUrl(text, { origin, bandSpaceId, resolve }) {
  const candidate = text.trim()
  if (candidate === '' || /\s/.test(candidate)) {
    return null
  }

  let url
  try {
    url = new URL(candidate)
  } catch {
    return null
  }
  if (url.origin !== origin) {
    return null
  }

  const route = resolve(url.pathname + url.search)
  if (route.params?.id !== bandSpaceId) {
    return null
  }

  for (const { type, route: routeName, param } of SEARCH_TYPES) {
    const value = route.query?.[param]
    if (route.name === routeName && typeof value === 'string' && UUID.test(value)) {
      return `${type}-${value.toLowerCase()}`
    }
  }

  return null
}
