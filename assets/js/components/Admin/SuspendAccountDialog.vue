<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="`Suspendre ${username}`"
    :style="{ width: '32rem' }"
    :breakpoints="{ '575px': '95vw' }"
    @hide="reset"
  >
    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <p class="text-sm text-surface-600 dark:text-surface-300">
        Le compte ne pourra plus se connecter et disparaît des recherches jusqu'à la levée de la
        suspension.
      </p>
      <div class="flex flex-col gap-2">
        <label for="suspension-reason" class="text-sm font-medium">Motif de la suspension</label>
        <Textarea
          id="suspension-reason"
          v-model="reason"
          rows="4"
          fluid
          :disabled="isSubmitting"
          :invalid="!!errorMessage"
        />
      </div>
      <Message v-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>
      <div class="flex justify-end gap-2">
        <Button label="Annuler" severity="secondary" text :disabled="isSubmitting" @click="visible = false" />
        <Button
          type="submit"
          label="Suspendre le compte"
          icon="pi pi-ban"
          severity="danger"
          :loading="isSubmitting"
          :disabled="!reason.trim() || isSubmitting"
        />
      </div>
    </form>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { ref } from 'vue'
import { handleApiError } from '../../api/utils/handleApiError.js'

/** Asks the reason a suspension needs, then hands it to `submit`, which does the call (#1116). */
const props = defineProps({
  username: { type: String, required: true },
  /** (reason: string) => Promise, rejected with the axios error on failure. */
  submit: { type: Function, required: true }
})

const emit = defineEmits(['suspended'])
const visible = defineModel('visible', { type: Boolean, default: false })

const reason = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')

async function handleSubmit() {
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    await props.submit(reason.value.trim())
    visible.value = false
    emit('suspended')
  } catch (e) {
    try {
      handleApiError(e)
    } catch (normalized) {
      errorMessage.value = normalized.message
    }
  } finally {
    isSubmitting.value = false
  }
}

function reset() {
  reason.value = ''
  errorMessage.value = ''
}
</script>
