<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Inscription requise"
    :style="{ width: '28rem' }"
  >
    <p class="text-surface-700 dark:text-surface-300 mb-6">
      {{ displayMessage }}
    </p>

    <!-- Prominent Google Sign-in -->
    <a
      :href="googleAuthUrl"
      class="flex items-center justify-center gap-3 w-full p-3 rounded-lg bg-primary hover:bg-primary-emphasis text-white font-medium transition-colors cursor-pointer no-underline mb-4"
    >
      <svg class="w-5 h-5" viewBox="0 0 24 24">
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
      <a
        @click="handleClassicSignup"
        class="text-primary hover:text-primary-emphasis cursor-pointer text-sm ml-1"
      >
        S'inscrire autrement
      </a>
    </div>

    <Divider />

    <!-- Login link -->
    <div class="text-center">
      <span class="text-surface-500 dark:text-surface-400 text-sm">Déjà un compte ?</span>
      <a
        @click="handleLogin"
        class="text-primary hover:text-primary-emphasis cursor-pointer text-sm ml-1 font-medium"
      >
        Se connecter
      </a>
    </div>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import Divider from 'primevue/divider'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  variant: {
    type: String,
    default: 'default' // 'see_more', 'contact', 'post_announce', 'default'
  },
  musicianName: {
    type: String,
    default: null
  },
  message: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:visible'])

const router = useRouter()

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

const displayMessage = computed(() => {
  // If a custom message is provided, use it
  if (props.message) {
    return props.message
  }

  // Otherwise, use variant-based messages
  switch (props.variant) {
    case 'see_more':
      return 'Inscrivez-vous pour voir tous les résultats et contacter les musiciens'
    case 'contact':
      return props.musicianName
        ? `Créez votre profil pour contacter ${props.musicianName}`
        : 'Créez votre profil pour contacter ce musicien'
    case 'post_announce':
      return 'Inscrivez-vous pour poster votre annonce'
    default:
      return 'Vous devez vous connecter pour effectuer cette action.'
  }
})

const currentReturnUrl = computed(() => {
  return window.location.href
})

const googleAuthUrl = computed(() => {
  return Routing.generate('oauth_google_start', { return_url: currentReturnUrl.value })
})

watch(() => props.visible, (newValue, oldValue) => {
  if (newValue) {
    trackUmamiEvent('auth-modal-shown', { variant: props.variant })
  } else if (oldValue) {
    trackUmamiEvent('auth-modal-closed', { variant: props.variant })
  }
})

function handleLogin() {
  emit('update:visible', false)
  router.push({ name: 'app_login', query: { return_url: currentReturnUrl.value } })
}

function handleClassicSignup() {
  emit('update:visible', false)
  router.push({ name: 'app_register', query: { return_url: currentReturnUrl.value } })
}
</script>
