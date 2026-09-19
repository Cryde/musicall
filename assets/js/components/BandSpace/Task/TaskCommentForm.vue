<template>
  <div>
    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-2">Commentaires</h4>

    <Message v-if="submitError" severity="error" :closable="false" class="mb-2">
      {{ submitError }}
    </Message>

    <MentionEditor
      v-model="content"
      :members="members"
      aria-label="Écrire un commentaire"
      placeholder="Écrire un commentaire..."
      editor-class="min-h-[4.5rem] max-h-60 rounded-lg p-3"
      submit-on="ctrl-enter"
      :disabled="isSubmitting"
      @submit="handleSubmit"
    />

    <div class="flex justify-end mt-2">
      <Button
        label="Envoyer"
        icon="pi pi-send"
        size="small"
        :loading="isSubmitting"
        :disabled="isEmpty || isSubmitting"
        @click="handleSubmit"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, ref } from 'vue'
import MentionEditor from '../../Global/MentionEditor.vue'

const props = defineProps({
  members: { type: Array, default: () => [] },
  /**
   * An awaitable rather than an emit, the same shape the chat composer takes, because the box has to
   * know whether the comment landed: it used to clear the moment you clicked, so a failed post cost
   * you the sentence you had typed. With mentions it would cost the sentence and every member you had
   * picked out of a dropdown.
   */
  submitComment: { type: Function, required: true },
  isSubmitting: { type: Boolean, default: false }
})

const content = ref('')
const submitError = ref('')
const isEmpty = computed(() => content.value.trim() === '')

async function handleSubmit() {
  if (isEmpty.value || props.isSubmitting) {
    return
  }

  submitError.value = ''

  try {
    await props.submitComment(content.value.trim())
    content.value = ''
  } catch (e) {
    console.error('Failed to post the comment:', e)
    submitError.value = e.message || "Le commentaire n'a pas pu être envoyé."
  }
}
</script>
