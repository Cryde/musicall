/**
 * The page `route` shows, in `locale`: a vue-router RouteLocationRaw to navigate to, or null when that
 * page has no version in that language. `route` is the current RouteLocationNormalizedLoaded (or a
 * RouteLocationNormalized, as a guard gets). The language switcher (#1193) navigates to the result.
 *
 * Always null for now: #1192 adds the /en routes and implements it.
 */
export function localizedLocation(_route, _locale) {
  return null
}
