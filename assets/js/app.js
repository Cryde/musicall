import axios from 'axios'
import { computed, createApp, nextTick, watch } from 'vue'
import '../style/style.css'
import { VueUmamiPlugin } from '@jaseeey/vue-umami-plugin'
import { createHead } from '@unhead/vue/client'
import { useTitle } from '@vueuse/core'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import Ripple from 'primevue/ripple'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'
import facebookLogoUrl from '../image/facebook-logo.jpg'
import App from './App.vue'
import { useDarkMode } from './composables/useDarkMode.js'
import { PRIME_VUE_CALENDAR_LOCALES } from './constants/primeVueLocale.js'
import { DEFAULT_TITLE_KEY, refreshedDefaultTitle } from './i18n/defaultTitle.js'
import { forActiveLocale } from './i18n/formatLocale.js'
import { DEFAULT_LOCALE, i18n } from './i18n/index.js'
import { setLocale } from './i18n/locale.js'
import router from './router/index.js'
import { useUserSecurityStore } from './store/user/security.js'
import MusicAllPreset from './theme/musicAllPreset.js'
import { hasLiveToken, isAuthEndpoint, refreshSession } from './utils/sessionRefresh.js'
import { isChunkLoadError, reloadOnceForStaleBuild } from './utils/staleBuild.js'

// Initialize dark mode before app mounts (detects system preference or uses saved cookie)
const { initialize: initDarkMode } = useDarkMode()
initDarkMode()

// The locale the server resolved for this page, applied before anything renders. French is in the
// bundle, so a French page waits on nothing. A catalogue that fails to load falls back to French, page
// language and API calls included, so the page does not show French text that it labels English.
try {
  await setLocale(document.documentElement.lang)
} catch (error) {
  console.error('Failed to load the page locale:', error)
  await setLocale(DEFAULT_LOCALE)
}

/**
 * A 401 means the token aged out, not that the session ended (#1008).
 *
 * The JWT lasts an hour and the browser deletes its cookie the moment it expires, while the refresh
 * token behind it is valid for thirty days. This used to clear `was_logged_in` and send the member to
 * the login page on the first 401, without ever calling the refresh endpoint, so a session the server
 * was perfectly willing to renew was thrown away. Worse, clearing that flag is what `checkAuthInfo()`
 * reads to decide whether to refresh at all, so even reloading the page could not get it back.
 *
 * So: renew once, send the request again, and only give up if the renewal itself fails.
 */
axios.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status !== 401) {
      return Promise.reject(error)
    }

    const config = error.config ?? {}
    if (isAuthEndpoint(config.url)) {
      return Promise.reject(error)
    }

    // Already renewed once for this request, so the token is demonstrably not what is being refused:
    // the renewal above succeeded, or we would not be here. Ending the session on a refusal that is
    // not about the session would log somebody out over whatever the server actually objected to,
    // and a session that really is over fails at the renewal, which is handled below.
    if (config.__sessionRetried) {
      return Promise.reject(error)
    }

    try {
      await refreshSession()
    } catch {
      // A refresh token is single use, so when two tabs renew at once one of them is refused. Its
      // cookies have been rewritten by the winner all the same, so a usable token here means there is
      // nothing wrong beyond having lost a race, and the request just needs sending again.
      if (!hasLiveToken()) {
        await endSession()

        return Promise.reject(error)
      }
    }

    config.__sessionRetried = true

    return axios(config)
  }
)

/**
 * Order matters here. The store has to know the session is over **before** the redirect, because
 * `app_login` is `isGuestOnly` and the router guard reads that flag: pushed the other way round, the
 * guard bounces the navigation back to the home page and the login form is never reached.
 */
async function endSession() {
  useUserSecurityStore().expireSession()

  if (router.currentRoute.value?.name === 'app_login') {
    return
  }

  await router.push({
    name: 'app_login',
    query: { redirect: router.currentRoute.value?.fullPath }
  })
}

const pinia = createPinia()
const app = createApp(App)

// Umami session replay (self-hosted recorder.js), loaded alongside the tracking plugin and gated on
// the same site id. data-website-id mirrors the tracker so the recording attaches to the right site
// per environment. The sampling/masking/duration knobs are inline for now.
const umamiRecorderScript =
  import.meta.env.VITE_UMAMI_SITE_ID && import.meta.env.VITE_UMAMI_RECORDER_SCRIPT
    ? [
        {
          src: import.meta.env.VITE_UMAMI_RECORDER_SCRIPT,
          defer: true,
          'data-website-id': import.meta.env.VITE_UMAMI_SITE_ID,
          'data-sample-rate': '0.15',
          'data-mask-level': 'moderate',
          'data-max-duration': '300000'
        }
      ]
    : []

const head = createHead({
  init: [
    {
      // No htmlAttrs.lang: the server renders it and setLocale() switches it, while unhead would put
      // its own value back on every render.
      meta: [
        { property: 'og:type', content: 'website' },
        {
          property: 'og:image',
          content: `${window.location.origin}${facebookLogoUrl}`
        },
        { property: 'og:site_name', content: 'MusicAll' }
      ],
      script: umamiRecorderScript
    }
  ]
})
// What `og:locale` calls each app locale. The bot pages have the same table (bot_base.html.twig).
const OG_LOCALES = { fr: 'fr_FR', en: 'en_GB' }

// The tags that follow the active locale, patched on a switch.
const localeHead = head.push(localeHeadInput())

function localeHeadInput() {
  return {
    meta: [
      {
        key: 'description',
        name: 'description',
        content: i18n.global.t('shared.head.description')
      },
      { property: 'og:locale', content: forActiveLocale(OG_LOCALES) }
    ]
  }
}

// The default title, until a page sets its own. Not in unhead, which writes its title again on each of its
// renders: after a switch, this one would replace the page's (useTitle).
useTitle(computed(() => i18n.global.t(DEFAULT_TITLE_KEY)))
// Once the next page has rendered, the title a page put back as it went is the active locale's default.
router.afterEach(async () => {
  await nextTick()
  const title = refreshedDefaultTitle(document.title, i18n.global)
  if (title) {
    document.title = title
  }
})
app.use(head)
app.use(i18n)
app.use(PrimeVue, {
  ripple: true,
  locale: forActiveLocale(PRIME_VUE_CALENDAR_LOCALES),
  theme: {
    preset: MusicAllPreset,
    options: {
      darkModeSelector: '.dark-mode'
    }
  }
})
// PrimeVue merges its locale in once, at install, so a later switch has to be copied into its config.
watch(i18n.global.locale, () => {
  Object.assign(
    app.config.globalProperties.$primevue.config.locale,
    forActiveLocale(PRIME_VUE_CALENDAR_LOCALES)
  )
  localeHead.patch(localeHeadInput())
})

if (import.meta.env.VITE_UMAMI_SITE_ID) {
  app.use(
    VueUmamiPlugin({
      websiteID: import.meta.env.VITE_UMAMI_SITE_ID,
      scriptSrc: import.meta.env.VITE_UMAMI_SITE_SCRIPT,
      // Capture Core Web Vitals in Umami's Performance tab (data-performance flag on the tracker script, since v3.1.0).
      extraDataAttributes: {
        'data-auto-track': 'true',
        'data-performance': 'true'
      }
    })
  )
}
app.directive('ripple', Ripple)
app.directive('tooltip', Tooltip)
app.use(ToastService)
app.use(ConfirmationService)
app.use(pinia)
app.use(router)
app.mount('#app')

// After a deploy, a tab opened before it asks for chunks that no longer exist (#1105). The reload
// goes where the user was heading: a failed route chunk means that navigation never happened.
let pendingNavigation = null
router.beforeEach((to) => {
  pendingNavigation = to.fullPath
})
// Only the navigation that finished: one cancelled by a newer one must not forget the newer target.
router.afterEach((to) => {
  if (to.fullPath === pendingNavigation) pendingNavigation = null
})

function reloadForStaleBuild() {
  return reloadOnceForStaleBuild({
    storage: window.sessionStorage,
    now: Date.now,
    load: (target) => (target ? window.location.assign(target) : window.location.reload()),
    url: pendingNavigation
  })
}

// Vite's signal, fired first, for a chunk or one of its dependencies that failed to load. Kept
// quiet only when it reloads.
window.addEventListener('vite:preloadError', (event) => {
  if (reloadForStaleBuild()) event.preventDefault()
})

// The same failure as the router sees it, for an import Vite did not wrap.
router.onError((error) => {
  if (isChunkLoadError(error)) reloadForStaleBuild()
  // A navigation that threw never reaches afterEach, and its target is dead either way.
  pendingNavigation = null
})

import.meta.glob(['../image/**'])
