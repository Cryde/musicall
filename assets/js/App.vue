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

const userSecurityStore = useUserSecurityStore()
const router = useRouter()

onMounted(async () => {
  await userSecurityStore.checkAuthInfo()
  const { isAuthenticated, isSuperAdmin } = storeToRefs(userSecurityStore)

  router.beforeResolve((to) => {
    // A route may name where a visitor without an account should land instead of the login form,
    // so a shared link to a gated module explains itself rather than presenting a bare password box.
    if (to.meta.isAuthRequired && !isAuthenticated.value) {
      return { name: to.meta.unauthenticatedRedirect ?? 'app_login' }
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
