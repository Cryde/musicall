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

    <SignupOptions @navigate="emit('update:visible', false)" />
  </Dialog>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Dialog from 'primevue/dialog'
import { computed, watch } from 'vue'
import SignupOptions from './SignupOptions.vue'

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

watch(
  () => props.visible,
  (newValue, oldValue) => {
    if (newValue) {
      trackUmamiEvent('auth-modal-shown', { variant: props.variant })
    } else if (oldValue) {
      trackUmamiEvent('auth-modal-closed', { variant: props.variant })
    }
  }
)
</script>
