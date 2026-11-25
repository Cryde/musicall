<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="header"
    :style="{ width: '60rem' }"
    :breakpoints="{ '1280px': '90vw', '768px': '95vw' }"
    :closable="!isSaving"
    :close-on-escape="!isSaving"
    @show="emit('show')"
    @hide="emit('hide')"
  >
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true" />
    </div>

    <div v-else-if="isLoaded" class="flex flex-col gap-8">
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <slot />
    </div>

    <template #footer>
      <Button :label="t('profile.services.cancel')" severity="secondary" text :disabled="isSaving" @click="visible = false" />
      <Button :label="t('profile.service_form.save')" icon="pi pi-check" :loading="isSaving" :disabled="!isLoaded" @click="emit('save')" />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import { useI18n } from 'vue-i18n'

/** A service's form dialog (#1410, #1411), with the state useServiceForm() keeps: its fields go in the slot. */
defineProps({
  header: { type: String, required: true },
  isLoading: { type: Boolean, required: true },
  /** Whether the form came back: its fields show, and it can be saved. */
  isLoaded: { type: Boolean, required: true },
  isSaving: { type: Boolean, required: true },
  error: { type: String, default: '' }
})
const emit = defineEmits(['show', 'hide', 'save'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
</script>
