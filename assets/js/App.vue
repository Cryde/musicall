<template>
    <router-view/>
    <ConfirmDialog />
</template>

<script setup>
import { storeToRefs } from 'pinia'
import ConfirmDialog from 'primevue/confirmdialog'
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserSecurityStore } from './store/user/security.js'
import { resolveUnauthenticatedRedirect } from './utils/unauthenticatedRedirect.js'

const userSecurityStore = useUserSecurityStore()
const router = useRouter()

onMounted(async () => {
  await userSecurityStore.checkAuthInfo()
  const { isAuthenticated, isSuperAdmin } = storeToRefs(userSecurityStore)

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
    // Hides modules that are merged but not yet announced. The API stays open, so this
    // keeps the URL from being walkable, it does not protect anything.
    if (to.meta.superAdminOnly && !isSuperAdmin.value) {
      return to.params.id
        ? { name: 'app_band_dashboard', params: { id: to.params.id } }
        : { name: 'app_home' }
    }
  })
})
</script>
