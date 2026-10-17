import * as Cookies from 'es-cookie'
import { LOCALE_SUGGESTIONS } from './localeSuggestion.js'
import { localizedLocation } from './localizedLocation.js'

// RequestLocaleResolver::COOKIE. Page requests go through no firewall, so the server never sees the
// account there: this cookie is what makes it render a page in the language the visitor chose.
export const LOCALE_COOKIE = 'locale'
const LOCALE_COOKIE_LIFETIME_DAYS = 365

export function readLocaleCookie(cookies = Cookies) {
  return cookies.get(LOCALE_COOKIE) ?? null
}

export function saveLocaleCookie(locale, cookies = Cookies) {
  cookies.set(LOCALE_COOKIE, locale, {
    expires: LOCALE_COOKIE_LIFETIME_DAYS,
    path: '/',
    sameSite: 'lax'
  })
}

/** A language's name in that language, capitalised as a label: "Français", "English". */
export function endonym(locale) {
  const name = new Intl.DisplayNames([locale], { type: 'language' }).of(locale)

  return name.charAt(0).toLocaleUpperCase(locale) + name.slice(1)
}

/**
 * The language the visitor chose: their account's when signed in, else the cookie's, in the order the
 * server reads them. Null when neither names an enabled locale.
 */
export function explicitLocale({ accountLocale = null, cookieLocale = null, enabledLocales }) {
  return [accountLocale, cookieLocale].find((locale) => enabledLocales.includes(locale)) ?? null
}

/**
 * Whether the visitor has a language of their own: any member does, since an account always has one,
 * and until the auth check answers a member cannot be told apart from a visitor.
 */
export function hasChosenLocale({ isAuthLoading, isSignedIn, cookieLocale, enabledLocales }) {
  return isAuthLoading || isSignedIn || explicitLocale({ cookieLocale, enabledLocales }) !== null
}

/**
 * The locale a URL names, from its first path segment: `/en/...` is English, while an unprefixed URL
 * names none, the default locale having no prefix. Until #1192 the router has no such URL, so this is
 * always null in practice; once it does, the router's own reading of the URL can replace this.
 */
export function localeNamedByPath(path, { enabledLocales, defaultLocale }) {
  const segment = path.split('/')[1]

  return segment !== defaultLocale && enabledLocales.includes(segment) ? segment : null
}

/**
 * What a page load does about the visitor's choice. `locale`: the chosen language to show the page in,
 * when it is not already in it. `cookie`: the account's language, to write into a cookie that says
 * otherwise, so the next page request renders in it from the start. With a single enabled locale
 * there is nothing to choose, so nothing is written either.
 *
 * A URL that names a locale (`urlLocale`, see localeNamedByPath) always wins: a shared `/en` link lands
 * where it points, and every account created before English existed says `fr` without anybody having
 * chosen it. Only an unprefixed URL is moved to the chosen language.
 */
export function arrivalPlan({
  pageLocale,
  urlLocale = null,
  accountLocale = null,
  cookieLocale = null,
  enabledLocales
}) {
  if (enabledLocales.length < 2) {
    return { locale: null, cookie: null }
  }

  const chosen = explicitLocale({ accountLocale, cookieLocale, enabledLocales })
  const accountDisagreesWithCookie =
    enabledLocales.includes(accountLocale) && accountLocale !== cookieLocale

  return {
    locale: urlLocale === null && chosen !== null && chosen !== pageLocale ? chosen : null,
    cookie: accountDisagreesWithCookie ? accountLocale : null
  }
}

/** Carries out an arrivalPlan, replacing the location so that going back does not undo it. */
export async function followArrivalPlan(plan, { saveCookie, ...navigation }) {
  if (plan.cookie) {
    saveCookie(plan.cookie)
  }
  if (plan.locale) {
    await showInLocale(plan.locale, { ...navigation, replace: true })
  }
}

/**
 * Switches to a language the visitor picked. It is saved first, on the account when signed in and then
 * in the cookie, and only then shown: the cookie waits for the account, so a refused save leaves both,
 * and the page, as they were.
 */
export async function switchLocale(
  locale,
  { saveAccountLocale = null, saveCookie, ...navigation }
) {
  if (saveAccountLocale) {
    await saveAccountLocale(locale)
  }
  saveCookie(locale)
  await showInLocale(locale, navigation)
}

/**
 * Shows the current page in `locale`. A page with a version in that language is navigated to, and the
 * router applies the locale its URL says; any other page switches the interface in place.
 */
export async function showInLocale(
  locale,
  { router, setLocale, replace = false, locate = localizedLocation }
) {
  const target = locate(router.currentRoute.value, locale)
  if (!target) {
    await setLocale(locale)
    return
  }

  await (replace ? router.replace(target) : router.push(target))
}

/**
 * The language to offer a visitor whose browser asks for another one than the page's, or null. Never
 * over a choice already made or a suggestion already dismissed, and only for an enabled locale the
 * suggestion is written in.
 */
export function suggestedLocale({
  browserLanguage,
  pageLocale,
  enabledLocales,
  hasExplicitChoice,
  dismissedLocale = null,
  suggestions = LOCALE_SUGGESTIONS
}) {
  const locale = browserLanguage?.split('-')[0].toLowerCase()
  const isOffered =
    Boolean(locale) && enabledLocales.includes(locale) && Object.hasOwn(suggestions, locale)

  if (hasExplicitChoice || !isOffered || locale === pageLocale || locale === dismissedLocale) {
    return null
  }

  return locale
}
