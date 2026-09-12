<template>
  <div class="border-t border-surface-200 dark:border-surface-700 p-4">
    <Message v-if="sendError" severity="error" :closable="false" class="mb-2">
      {{ sendError }}
    </Message>

    <div class="flex gap-2 items-end">
      <Textarea
        ref="messageInput"
        v-model="content"
        rows="2"
        class="flex-1 resize-none"
        placeholder="Votre message..."
        aria-label="Votre message"
        :disabled="chatStore.isSending"
        @keydown.enter.exact.prevent="send"
      />
      <Button
        icon="pi pi-send"
        aria-label="Envoyer le message"
        :loading="chatStore.isSending"
        :disabled="!content.trim() || chatStore.isSending"
        @click="send"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { nextTick, ref } from 'vue'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['sent'])

const chatStore = useBandSpaceChatStore()
const content = ref('')
const sendError = ref('')
const messageInput = ref(null)

async function send() {
  if (!content.value.trim() || chatStore.isSending) {
    return
  }

  sendError.value = ''

  try {
    await chatStore.sendMessage(props.bandSpaceId, content.value)
    content.value = ''
    emit('sent')
    nextTick(() => messageInput.value?.$el?.focus())
  } catch (e) {
    sendError.value = errorMessageFor(e)
  }
}

/**
 * handleApiError already carries the server's own French sentence, so a message too long or a space
 * in its deletion grace period explains itself. Only the rate limiter needs wording of our own, since
 * its 429 body says nothing a member could act on.
 */
function errorMessageFor(error) {
  if (error.status === 429) {
    return 'Trop de messages envoyés. Veuillez patienter un instant.'
  }

  console.error('Failed to send the message:', error)

  return error.message || "Le message n'a pas pu être envoyé."
}
</script>
