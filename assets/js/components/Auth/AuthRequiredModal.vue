<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('auth_modal.title')"
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
import { useI18n } from 'vue-i18n'
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
const { t } = useI18n()

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
      return t('auth_modal.see_more')
    case 'contact':
      return props.musicianName
        ? t('auth_modal.contact_musician', { name: props.musicianName })
        : t('auth_modal.contact')
    case 'post_announce':
      return t('auth_modal.post_announce')
    default:
      return t('auth_modal.default')
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
