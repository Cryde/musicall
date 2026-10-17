import { defineComponent, h, shallowRef, watch } from 'vue'
import { DEFAULT_LOCALE, i18n } from './index.js'

/**
 * A route component written once per locale, `{ fr: () => import(...), en: () => import(...) }`,
 * for pages too long for the catalogue. Only the active locale's version is downloaded.
 */
export function localizedPage(loaders) {
  const versions = {}

  function shownLocale() {
    const locale = i18n.global.locale.value

    return Object.hasOwn(loaders, locale) ? locale : DEFAULT_LOCALE
  }

  async function load(locale) {
    versions[locale] ??= (await loaders[locale]()).default
  }

  return defineComponent({
    name: 'LocalizedPage',
    // Awaited on every navigation, so a hash like `/terms#recours` finds its target when the router
    // scrolls. A version that fails to download rejects the navigation, like any lazy route.
    beforeRouteEnter: () => load(shownLocale()),
    setup() {
      const shown = shallowRef(versions[shownLocale()])

      // A locale switched while the page is open keeps the current version until the other loads.
      watch(shownLocale, async (locale) => {
        try {
          await load(locale)
          if (locale === shownLocale()) shown.value = versions[locale]
        } catch (error) {
          console.error(`The ${locale} version of this page failed to load`, error)
        }
      })

      return () => (shown.value ? h(shown.value) : null)
    }
  })
}
