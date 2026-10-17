import axios from 'axios'
import { DEFAULT_LOCALE, i18n } from './index.js'

// Sent with every API call. The server reads it before the account, the cookie and Accept-Language
// (RequestLocaleResolver::HEADER), so an English page gets English data whatever the browser says.
export const LOCALE_HEADER = 'X-Locale'

// French ships in the main bundle. Any other catalogue is a chunk of its own, fetched the first time
// it is needed, so a French visitor never downloads it.
const LAZY_CATALOGUES = {
  en: () => import('./en/index.js')
}

/** The locales `data-enabled-locales` lists, French only when the attribute is absent. */
export function enabledLocalesOf(root) {
  return root?.dataset.enabledLocales?.split(',') ?? [DEFAULT_LOCALE]
}

// The server leaves the attribute out while French is the only enabled locale.
export const ENABLED_LOCALES = enabledLocalesOf(globalThis.document?.documentElement)

let requestedLocale = DEFAULT_LOCALE

/**
 * Switches the interface to an enabled locale: its catalogue, `<html lang>` and the locale API calls
 * ask for. A locale that is not enabled, or that has no catalogue, is ignored.
 */
export async function setLocale(locale) {
  if (!ENABLED_LOCALES.includes(locale) || !hasCatalogue(locale)) {
    return
  }

  requestedLocale = locale
  if (!i18n.global.availableLocales.includes(locale)) {
    const { default: messages } = await LAZY_CATALOGUES[locale]()
    i18n.global.setLocaleMessage(locale, messages)
  }
  // Another switch was asked for while this catalogue loaded, and the last one asked for wins.
  if (locale !== requestedLocale) {
    return
  }

  i18n.global.locale.value = locale
  document.documentElement.lang = locale
  axios.defaults.headers.common[LOCALE_HEADER] = locale
}

function hasCatalogue(locale) {
  return locale === DEFAULT_LOCALE || Object.hasOwn(LAZY_CATALOGUES, locale)
}
