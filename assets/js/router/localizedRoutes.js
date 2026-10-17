import {
  isLocalizedRoute,
  LOCALE_PARAM_PATH,
  localeOfPath,
  PREFIXED_LOCALES,
  pathInLocale,
  segmentInLocale
} from '../i18n/localizedPath.js'

/**
 * The route grouping the pages that have a version per locale (#1192). Each page is written with its French
 * path, which stays as it is; the group puts it under the locale param, and gives it an alias when its first
 * segment is translated, so `/en/lessons` is the same route as `/cours`. A page keeps one name in every
 * language, and a `{ name }` link inherits the locale of the page it is on.
 */
export function localizedRoutes(records) {
  return { path: LOCALE_PARAM_PATH, children: records.map(localizedRecord) }
}

/**
 * Has `router.resolve()`, which RouterLink renders its href with, give a localized page the path its URL's locale
 * names. A `{ name }` link resolves to the record's own, French, path: on an English page that is `/en/cours`,
 * where crawlers must read `/en/lessons`. A navigation resolves through the router's internal copy, which the
 * router guard corrects the same way.
 */
export function withLocalizedHrefs(router) {
  const resolve = router.resolve
  router.resolve = (to, currentLocation) => {
    const route = resolve(to, currentLocation)
    if (!isLocalizedRoute(route)) {
      return route
    }

    const path = pathInLocale(route.path, localeOfPath(route.path))

    return path === route.path
      ? route
      : resolve({ path, query: route.query, hash: route.hash }, currentLocation)
  }

  return router
}

function localizedRecord(record) {
  const path = record.path.slice(1)
  const [firstSegment, ...rest] = path.split('/')
  const aliases = PREFIXED_LOCALES.map((locale) =>
    [segmentInLocale(firstSegment, locale), ...rest].join('/')
  ).filter((alias) => alias !== path)

  return aliases.length > 0 ? { ...record, path, alias: aliases } : { ...record, path }
}
