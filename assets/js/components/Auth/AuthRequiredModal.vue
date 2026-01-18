<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Connexion requise"
    :style="{ width: '25rem' }"
  >
    <p class="text-surface-700 dark:text-surface-300 mb-4">
      {{ message }}
    </p>
    <p class="text-surface-600 dark:text-surface-400 text-sm">
      Si vous ne disposez pas de compte, vous pouvez vous inscrire gratuitement sur le site.
    </p>

    <div class="flex justify-end gap-2 mt-6">
      <Button
        type="button"
        label="Annuler"
        severity="secondary"
        @click="handleClose"
      />
      <Button
        type="button"
        label="S'inscrire"
        severity="info"
        outlined
        @click="handleRegister"
      />
      <Button
        type="button"
        label="Se connecter"
        icon="pi pi-sign-in"
        @click="handleLogin"
      />
    </div>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  message: {
    type: String,
    default: 'Vous devez vous connecter pour effectuer cette action.'
  }
})

const emit = defineEmits(['update:visible'])

const router = useRouter()

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

watch(() => props.visible, (newValue) => {
  if (newValue) {
    trackUmamiEvent('auth-modal-shown')
  }
})

function handleClose() {
  emit('update:visible', false)
}

function handleLogin() {
  emit('update:visible', false)
  router.push({ name: 'app_login' })
}

function handleRegister() {
  emit('update:visible', false)
  // TODO: Update route name when registration page is created
  router.push({ name: 'app_register' })
}
</script>
