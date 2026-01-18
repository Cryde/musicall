import axios from 'axios'
import { createApp } from 'vue'
import '../style/style.css'
import { createHead } from '@unhead/vue/client'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import Ripple from 'primevue/ripple'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'
import App from './App.vue'
import { useDarkMode } from './composables/useDarkMode.js'
import router from './router/index.js'
import MusicAllPreset from './theme/musicAllPreset.js'
import { VueUmamiPlugin } from '@jaseeey/vue-umami-plugin';

// Initialize dark mode before app mounts (detects system preference or uses saved cookie)
const { initialize: initDarkMode } = useDarkMode()
initDarkMode()

// Global axios interceptor for 401 errors
axios.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Don't redirect if already on login page or if it's the login request itself
      const isLoginRequest = error.config?.url?.includes('login')
      const isRefreshRequest = error.config?.url?.includes('refresh')
      const isOnLoginPage = router.currentRoute.value?.name === 'app_login'

      if (!isLoginRequest && !isRefreshRequest && !isOnLoginPage) {
        // Clear auth state
        localStorage.removeItem('was_logged_in')

        // Redirect to login
        await router.push({
          name: 'app_login',
          query: { redirect: router.currentRoute.value?.fullPath }
        })
      }
    }
    return Promise.reject(error)
  }
)

const pinia = createPinia()
const app = createApp(App)
const head = createHead({
  init: [
    {
      title: 'MusicAll, le site de référence au service de la musique',
      htmlAttrs: {
        lang: 'fr'
      },
      meta: [
        {
          vmid: 'description',
          name: 'description',
          content:
            'Site communautaire au service de la musique. Articles et cours sur la musique, annuaire de musiciens, forums, ...'
        },
        { property: 'og:type', content: 'website' },
        { property: 'og:locale', content: 'fr_FR' },
        {
          property: 'og:image',
          content: `${window.location.origin}/build/images/facebook-logo.jpg`
        },
        { property: 'og:site_name', content: 'MusicAll' }
      ]
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
            router,
        })
    );
}
if (import.meta.env.VITE_GOOGLE_GTAG_ID) {
    const hasChoice = localStorage.getItem('cookie_consent_choice') !== null
    let consMode = 'denied'
    if (hasChoice && localStorage.getItem('cookie_consent_choice') === 'accepted') {
        consMode = 'granted'
    }

    const { configure } = await import('vue-gtag')
    configure({
        tagId: import.meta.env.VITE_GOOGLE_GTAG_ID,
        initMode: 'auto',
        pageTracker: {
            router
        },
        consentMode: consMode,
        config: {
            anonymize_ip: true
        }
    })
}
app.directive('ripple', Ripple)
app.directive('tooltip', Tooltip)
app.use(ToastService)
app.use(ConfirmationService)
app.use(pinia)
app.use(router)
app.mount('#app')

import.meta.glob(['../image/**'])
