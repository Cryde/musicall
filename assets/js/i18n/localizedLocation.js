import { DEFAULT_LOCALE } from './index.js'
import { ENABLED_LOCALES } from './locale.js'
import { isLocalizedRoute, localeOfPath, pathInLocale } from './localizedPath.js'

// A forum and its topics are written in their forum's locale and have no version in another one: they live
// under the prefix of their content's locale instead.
const OWN_LANGUAGE_ROUTES = new Set(['forum_topic_list', 'forum_topic_item'])

/**
 * The page `route` shows, in `locale`: a vue-router location to navigate to, or null when that page has no
 * version in that language (a page without prefix, a forum, a topic) or `locale` is not enabled. `route` is
 * the current RouteLocationNormalizedLoaded (or a RouteLocationNormalized, as a guard gets). The language
 * switcher (#1193) navigates to the result, and the router guard then applies its locale.
 *
 * A publication may be a text in one language: its page then redirects to that language's prefix, see
 * contentLanguageLocation().
 */
export function localizedLocation(route, locale) {
  if (
    !ENABLED_LOCALES.includes(locale) ||
    !isLocalizedRoute(route) ||
    OWN_LANGUAGE_ROUTES.has(route.name)
  ) {
    return null
  }

  return withPath(route, pathInLocale(route.path, locale))
}

/**
 * Where a page showing content written in `contentLocale` belongs: under that locale's prefix. Null when it is
 * already there, and for content shown in every language (no locale, a video) or in a locale not enabled.
 *
 * `loadedPath` is the route's path when the content's load started. Null too once the visitor has moved on, or
 * a late response would take them back to what they left.
 */
export function contentLanguageLocation(route, contentLocale, loadedPath) {
  if (route.path !== loadedPath || !ENABLED_LOCALES.includes(contentLocale)) {
    return null
  }

  const path = pathInLocale(route.path, contentLocale)

  return path === route.path ? null : withPath(route, path)
}

/**
 * Where the router guard sends a navigation to a localized page, or null to let it through, once the path
 * names the locale the page is shown in.
 *
 * On arrival, and for a navigation that changes language (the switcher, a link to a translation, the redirect
 * to a content's language, Back across a switch), the URL decides. Any other in-app link follows the active
 * locale: a `{ name }` link resolves to French on a page without prefix, and must not undo a switch to English
 * made there. A page of a locale that is not enabled goes to its French version.
 */
export function localeRedirect(to, from, activeLocale) {
  if (!isLocalizedRoute(to)) {
    return null
  }

  const path = pathInLocale(to.path, shownLocale(to, from, activeLocale))

  return path === to.path ? null : withPath(to, path)
}

function shownLocale(to, from, activeLocale) {
  const urlLocale = localeOfPath(to.path)
  if (!ENABLED_LOCALES.includes(urlLocale)) {
    return DEFAULT_LOCALE
  }

  // START_LOCATION, the `from` of the first navigation, matches nothing.
  const isArrival = from.matched.length === 0
  const changesLanguage = isLocalizedRoute(from) && localeOfPath(from.path) !== urlLocale
  if (isArrival || changesLanguage || OWN_LANGUAGE_ROUTES.has(to.name)) {
    return urlLocale
  }

  return activeLocale
}

function withPath(route, path) {
  return { path, query: route.query, hash: route.hash }
}
