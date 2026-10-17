import { DEFAULT_LOCALE } from './index.js'
import { ENABLED_LOCALES } from './locale.js'

/**
 * Where a page lives in each locale. French, the default, has no prefix; every other locale has its pages under
 * `/{locale}`, and a few landing pages translate their first segment too, so `/cours` is `/en/lessons`.
 * src/Locale/LocalizedPath.php is the server's copy, and tests/Fixtures/Locale/localized_paths.json keeps the
 * two in step.
 *
 * The router's paths are built from these locales, enabled or not: the router guard sends a page of a locale
 * that is not enabled to its French version. Translated segments only exist for an enabled locale, so
 * `/lessons` is nothing while English is off. A prefix is read whatever its case, so that the guard can send
 * `/EN/...` to `/en/...`: the router matches paths case insensitively.
 */
export const PREFIXED_LOCALES = ['en']

/** The path segment every localized route starts with: `/:locale(en)?/publications`. */
export const LOCALE_PARAM_PATH = `:locale(${PREFIXED_LOCALES.join('|')})?`

// French segment -> its translation, per locale. Only the landing pages people search for by name.
const TRANSLATED_SEGMENTS = {
  en: {
    cours: 'lessons',
    decouvrir: 'discover',
    'mentions-legales': 'legal-notice',
    'supprimer-mon-compte': 'delete-my-account',
    'rechercher-un-musicien': 'find-a-musician',
    'rechercher-un-professeur': 'find-a-teacher',
    'rechercher-un-guitariste': 'find-a-guitarist',
    'rechercher-un-batteur': 'find-a-drummer',
    'rechercher-un-bassiste': 'find-a-bassist',
    'rechercher-un-chanteur': 'find-a-singer',
    'rechercher-un-pianiste': 'find-a-pianist'
  }
}

/** The locale a path's prefix names, French when it has none. */
export function localeOfPath(path) {
  return prefixLocaleOf(path.split('/')[1]) ?? DEFAULT_LOCALE
}

/**
 * The path of the same page in `locale`, from its path in any locale. Also what corrects a path that mixes
 * the two, such as `/en/cours`, which a link inheriting the page's locale produces.
 */
export function pathInLocale(path, locale) {
  const segments = path.split('/').filter(Boolean)
  if (prefixLocaleOf(segments[0])) {
    segments.shift()
  }
  if (segments.length > 0) {
    segments[0] = segmentInLocale(segments[0], locale)
  }
  if (locale !== DEFAULT_LOCALE) {
    segments.unshift(locale)
  }

  return `/${segments.join('/')}`
}

/** A first path segment in `locale`, from its form in any enabled locale. */
export function segmentInLocale(segment, locale) {
  const frenchSegment = frenchSegmentOf(segment)

  return enabledTranslations(locale)?.[frenchSegment] ?? frenchSegment
}

function frenchSegmentOf(segment) {
  for (const locale of PREFIXED_LOCALES) {
    const translations = enabledTranslations(locale) ?? {}
    const french = Object.keys(translations).find((key) => translations[key] === segment)
    if (french) {
      return french
    }
  }

  return segment
}

function enabledTranslations(locale) {
  return ENABLED_LOCALES.includes(locale) ? TRANSLATED_SEGMENTS[locale] : undefined
}

function prefixLocaleOf(segment) {
  const locale = segment?.toLowerCase()

  return PREFIXED_LOCALES.includes(locale) ? locale : null
}

/** Whether the route is one of the pages with a version per locale, rather than a page without prefix. */
export function isLocalizedRoute(route) {
  return route.matched.at(-1)?.path.startsWith(`/${LOCALE_PARAM_PATH}`) ?? false
}
