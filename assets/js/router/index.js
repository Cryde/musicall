import { createRouter, createWebHistory } from 'vue-router'
import { i18n } from '../i18n/index.js'
import { setLocale } from '../i18n/locale.js'
import { localeRedirect } from '../i18n/localizedLocation.js'
import { isLocalizedRoute, localeOfPath } from '../i18n/localizedPath.js'
import { withLocalizedHrefs } from './localizedRoutes.js'
import routes from './routes.js'
import { keepsScrollPosition, rememberScrollRestore } from './scrollPosition.js'

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    rememberScrollRestore(savedPosition)
    // Restore scroll position on back/forward navigation
    if (savedPosition) {
      return savedPosition
    }
    // A footer link to one Band Space module lands on the demo, already on that tab
    if (to.name === 'app_band_space_presentation' && to.query.module) {
      return { el: '#band-space-demo', top: 24 }
    }
    // A report decision in the bell leads to how to contest it (#1125)
    if (to.name === 'app_terms' && to.hash) {
      return { el: to.hash, top: 24 }
    }
    if (keepsScrollPosition(to, from)) {
      return false
    }
    // Scroll to top for new navigations
    return { top: 0 }
  }
})

// A `{ name }` link renders the path its locale names, `/en/lessons` rather than `/en/cours`.
withLocalizedHrefs(router)

// A localized page is shown in its URL's locale, applied before its component loads, so that the page renders
// and asks the API in that language. The first navigation included: the language switcher (#1193) reads the
// active locale once the router is ready. A page without prefix keeps the active locale.
router.beforeEach(async (to, from) => {
  const redirect = localeRedirect(to, from, i18n.global.locale.value)
  if (redirect) {
    return redirect
  }
  if (isLocalizedRoute(to)) {
    await setLocale(localeOfPath(to.path))
  }
})

export default router
