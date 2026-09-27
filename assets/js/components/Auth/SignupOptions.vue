<template>
  <div>
    <!-- Prominent Google Sign-in -->
    <a
      :href="googleAuthUrl"
      class="flex items-center justify-center gap-3 w-full p-3 rounded-lg bg-primary hover:bg-primary-emphasis text-white font-medium transition-colors cursor-pointer no-underline mb-4"
    >
      <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
        <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
      </svg>
      <span>S'inscrire avec Google</span>
    </a>

    <!-- Secondary signup link -->
    <div class="text-center mb-4">
      <span class="text-surface-500 dark:text-surface-400 text-sm">Pas de compte Google ?</span>
      <router-link
        :to="{ name: 'app_register', query: { return_url: returnUrl } }"
        class="text-primary hover:text-primary-emphasis text-sm ml-1"
        @click="$emit('navigate')"
      >
        S'inscrire avec un email
      </router-link>
    </div>

    <Divider />

    <!-- Login link -->
    <div class="text-center">
      <span class="text-surface-500 dark:text-surface-400 text-sm">Déjà un compte ?</span>
      <router-link
        :to="{ name: 'app_login', query: { return_url: returnUrl } }"
        class="text-primary hover:text-primary-emphasis text-sm ml-1 font-medium"
        @click="$emit('navigate')"
      >
        Se connecter
      </router-link>
    </div>
  </div>
</template>

<script setup>
import Divider from 'primevue/divider'
import { computed } from 'vue'
import { useRoute } from 'vue-router'

/**
 * Google first, then the other ways in, all bringing the visitor back to the page they are on.
 * Relative, because the login page refuses an absolute return address and dropped the visitor on
 * the homepage after an email sign in (#1084).
 */
defineEmits(['navigate'])

const route = useRoute()
const returnUrl = computed(() => route.fullPath)
const googleAuthUrl = computed(() =>
  Routing.generate('oauth_google_start', { return_url: returnUrl.value })
)
</script>
