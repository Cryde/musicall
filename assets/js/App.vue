<template>
    <router-view/>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserSecurityStore } from './store/user/security.js'

const userSecurityStore = useUserSecurityStore()
const router = useRouter()

onMounted(async () => {
  await userSecurityStore.checkAuthInfo()
  const { isAuthenticated } = storeToRefs(userSecurityStore)

  router.beforeResolve((to) => {
    if (!!to.meta.isAuthRequired && !isAuthenticated.value) {
      return { name: 'app_login' }
    }
  })
})
</script>
