import { until, useLocalStorage } from '@vueuse/core'
import { useToast } from 'primevue/usetoast'
import { computed, readonly, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import securityApi from '../api/user/security.js'
import { DEFAULT_LOCALE, i18n } from '../i18n/index.js'
import { ENABLED_LOCALES, setLocale } from '../i18n/locale.js'
import {
  arrivalPlan,
  endonym,
  followArrivalPlan,
  hasChosenLocale,
  localeNamedByPath,
  readLocaleCookie,
  saveLocaleCookie,
  suggestedLocale,
  switchLocale
} from '../i18n/localeChoice.js'
import { LOCALE_SUGGESTIONS } from '../i18n/localeSuggestion.js'
import { useUserSecurityStore } from '../store/user/security.js'

// False while French is the only enabled locale. Everything below is then skipped, the cookie read and
// the language names included, so production does no work at all for a choice nobody can make.
export const CAN_CHOOSE_LOCALE = ENABLED_LOCALES.length > 1

/** The enabled locales, each named in its own language. */
export const LOCALE_OPTIONS = CAN_CHOOSE_LOCALE
  ? ENABLED_LOCALES.map((locale) => ({ locale, label: endonym(locale) }))
  : []

// A member whose profile failed to load would otherwise hold the arrival rule back for good.
const ACCOUNT_LOCALE_TIMEOUT_MS = 3000
const SUGGESTION_DISMISSED_KEY = 'locale_suggestion_dismissed'

// Mirrored here because a cookie is not reactive: the banner has to see a choice made from the header.
const cookieLocale = ref(CAN_CHOOSE_LOCALE ? readLocaleCookie() : null)
// Shared by every switcher: two quick choices would otherwise finish their saves in either order.
const isSwitching = ref(false)

function rememberLocale(locale) {
  saveLocaleCookie(locale)
  cookieLocale.value = locale
}

/** Switching language from the header, the settings or the banner: saved first, then shown. */
export function useLocaleChoice() {
  const { t, locale: activeLocale } = useI18n()
  const router = useRouter()
  const toast = useToast()
  const userSecurityStore = useUserSecurityStore()

  async function chooseLocale(locale) {
    if (isSwitching.value) {
      return
    }

    isSwitching.value = true
    try {
      await switchLocale(locale, {
        saveAccountLocale: userSecurityStore.isAuthenticated ? securityApi.changeLocale : null,
        saveCookie: rememberLocale,
        router,
        setLocale
      })
    } catch (error) {
      console.error('Failed to switch the locale:', error)
      toast.add({ severity: 'error', summary: t('locale_switcher.error'), life: 5000 })
    } finally {
      isSwitching.value = false
    }
  }

  return { activeLocale, isSwitching: readonly(isSwitching), chooseLocale }
}

/**
 * Applies the visitor's choice of language once per page load, so signing in, which reloads the page,
 * applies the account's. Called once the auth check has answered: a member's choice is their account's,
 * which the server never sees on a page request and which `X-Locale` would otherwise outrank on every
 * API call, so it waits for the profile.
 */
export async function honourLocaleChoice(router, userSecurityStore) {
  if (!CAN_CHOOSE_LOCALE) {
    return
  }

  try {
    const accountLocale = userSecurityStore.isAuthenticated
      ? await until(() => userSecurityStore.userProfile?.locale).toBeTruthy({
          timeout: ACCOUNT_LOCALE_TIMEOUT_MS,
          throwOnTimeout: false
        })
      : null
    await router.isReady()

    const plan = arrivalPlan({
      pageLocale: i18n.global.locale.value,
      urlLocale: localeNamedByPath(router.currentRoute.value.path, {
        enabledLocales: ENABLED_LOCALES,
        defaultLocale: DEFAULT_LOCALE
      }),
      accountLocale: accountLocale ?? null,
      cookieLocale: cookieLocale.value,
      enabledLocales: ENABLED_LOCALES
    })
    await followArrivalPlan(plan, { saveCookie: rememberLocale, router, setLocale })
  } catch (error) {
    console.error('Failed to apply the chosen locale:', error)
  }
}

/**
 * The banner offering a visitor the language their browser asks for, until they choose or dismiss it.
 * Only for a component mounted when CAN_CHOOSE_LOCALE: it reads localStorage and listens to it.
 */
export function useLocaleSuggestion() {
  const userSecurityStore = useUserSecurityStore()
  const { chooseLocale } = useLocaleChoice()
  const dismissedLocale = useLocalStorage(SUGGESTION_DISMISSED_KEY, null)

  const suggestion = computed(() => {
    const locale = suggestedLocale({
      browserLanguage: globalThis.navigator?.language,
      pageLocale: i18n.global.locale.value,
      enabledLocales: ENABLED_LOCALES,
      hasExplicitChoice: hasChosenLocale({
        isAuthLoading: userSecurityStore.isAuthenticatedLoading,
        isSignedIn: userSecurityStore.isAuthenticated,
        cookieLocale: cookieLocale.value,
        enabledLocales: ENABLED_LOCALES
      }),
      dismissedLocale: dismissedLocale.value
    })

    return locale ? { locale, ...LOCALE_SUGGESTIONS[locale] } : null
  })

  function accept() {
    return chooseLocale(suggestion.value.locale)
  }

  function dismiss() {
    dismissedLocale.value = suggestion.value.locale
  }

  return { suggestion, accept, dismiss }
}
