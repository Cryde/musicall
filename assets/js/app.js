import axios from 'axios'
import { createApp } from 'vue'
import '../style/style.css'
import { VueUmamiPlugin } from '@jaseeey/vue-umami-plugin'
import { createHead } from '@unhead/vue/client'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import Ripple from 'primevue/ripple'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'
import facebookLogoUrl from '../image/facebook-logo.jpg'
import App from './App.vue'
import { useDarkMode } from './composables/useDarkMode.js'
import router from './router/index.js'
import { useUserSecurityStore } from './store/user/security.js'
import MusicAllPreset from './theme/musicAllPreset.js'
import { hasLiveToken, isAuthEndpoint, refreshSession } from './utils/sessionRefresh.js'

// Initialize dark mode before app mounts (detects system preference or uses saved cookie)
const { initialize: initDarkMode } = useDarkMode()
initDarkMode()

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

    // Already renewed once for this request, so whatever is being refused is not a stale token.
    // Notably it need not be an authentication problem at all: `AccessDeniedException` is mapped to
    // 401 in api_platform.yaml, and parts of the message domain throw it for somebody who is signed
    // in but not allowed, so ending the session here would log a member out over a stale bookmark.
    // A session that really is dead fails at the renewal above, which is where it is handled.
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
      title: 'MusicAll, le site de référence au service de la musique',
      htmlAttrs: {
        lang: 'fr'
      },
      meta: [
        {
          key: 'description',
          name: 'description',
          content:
            'Site communautaire au service de la musique. Articles et cours sur la musique, annuaire de musiciens, forums, ...'
        },
        { property: 'og:type', content: 'website' },
        { property: 'og:locale', content: 'fr_FR' },
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
app.use(head)
app.use(PrimeVue, {
  ripple: true,
  theme: {
    preset: MusicAllPreset,
    options: {
      darkModeSelector: '.dark-mode'
    }
  }
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

import.meta.glob(['../image/**'])
