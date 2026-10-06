<template>
    <router-view/>
    <!-- Without a width the dialog grows to fit its message, so a long one spans the whole screen.
         The message is plain text, so line breaks are how it splits into paragraphs. -->
    <ConfirmDialog
      :style="{ width: '32rem' }"
      :breakpoints="{ '575px': '95vw' }"
      :pt="{ icon: { class: 'self-start mt-1' }, message: { class: 'whitespace-pre-line' } }"
    />
    <!-- Mounted here rather than in a layout so the trigger works from every route, the layout-less
         live setlist view included. -->
    <FeedbackDrawer />
</template>

<script setup>
import { storeToRefs } from 'pinia'
import ConfirmDialog from 'primevue/confirmdialog'
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import FeedbackDrawer from './components/Feedback/FeedbackDrawer.vue'
import { useUserSecurityStore } from './store/user/security.js'
import { resolveUnauthenticatedRedirect } from './utils/unauthenticatedRedirect.js'

const userSecurityStore = useUserSecurityStore()
const router = useRouter()

onMounted(async () => {
  await userSecurityStore.checkAuthInfo()
  const { isAuthenticated, isAdmin, isTester } = storeToRefs(userSecurityStore)

  router.beforeResolve((to) => {
    // Where an unauthenticated visitor lands, and whether the destination is told where they were
    // going. Extracted so the two carve-outs it encodes are pinned by tests.
    //
    // Following a return_url is gated separately by whoever consumes it: the store checks
    // isSafeReturnUrl before touching window.location, and the OAuth start route is validated
    // server side. This guard only ever produces a same-origin path from the router itself.
    if (to.meta.isAuthRequired && !isAuthenticated.value) {
      return resolveUnauthenticatedRedirect(to)
    }
    if (to.meta.isGuestOnly && isAuthenticated.value) {
      return { name: 'app_home' }
    }
    // The admin SPA used to be gated by isAuthRequired alone, so any logged-in account rendered the
    // whole back office. Nothing leaked, because every admin endpoint answers 403, but the views
    // turned that 403 into their empty state: a non admin was shown a plausible, entirely fictional
    // admin panel (#940).
    if (to.meta.isAdminRequired && !isAdmin.value) {
      return { name: 'app_home' }
    }
    // Hides modules that are merged but not yet announced. The API stays open, so this
    // keeps the URL from being walkable, it does not protect anything.
    if (to.meta.testerOnly && !isTester.value) {
      return to.params.id
        ? { name: 'app_band_dashboard', params: { id: to.params.id } }
        : { name: 'app_home' }
    }
  })
})
</script>
