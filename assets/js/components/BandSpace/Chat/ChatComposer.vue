<template>
  <div class="border-t border-surface-200 dark:border-surface-700 p-4">
    <Message v-if="sendError" severity="error" :closable="false" class="mb-2">
      {{ sendError }}
    </Message>

    <div class="flex gap-2 items-end">
      <MentionEditor
        ref="composer"
        v-model="content"
        class="flex-1"
        :members="suggestionSource"
        aria-label="Votre message"
        placeholder="Votre message..."
        editor-class="min-h-[3.5rem] max-h-40 rounded-md px-3 py-2"
        submit-on="enter"
        :disabled="isSending"
        @submit="send"
      />
      <Button
        icon="pi pi-send"
        aria-label="Envoyer le message"
        :loading="isSending"
        :disabled="isEmpty || isSending"
        @click="send"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, nextTick, ref } from 'vue'
import MentionEditor from '../../Global/MentionEditor.vue'

const props = defineProps({
  members: { type: Array, default: () => [] },
  /**
   * Where the message goes, supplied by the parent rather than reached for here (#994): the band
   * space tab sends through its own store, the inbox through the one holding the open conversation.
   * Both end at the same endpoint, and the editor below does not need to know which.
   */
  sendMessage: { type: Function, required: true },
  isSending: { type: Boolean, default: false }
})

const emit = defineEmits(['sent'])

const composer = ref(null)
/** What gets sent: the composer's content in the stored `@[uuid]` format, never what is on screen. */
const content = ref('')
const sendError = ref('')
const isEmpty = computed(() => content.value.trim() === '')

/**
 * `@tous` rides the roster as a pretend member, so it is picked and chipped like anybody else. The
 * hint travels with it rather than as a prop, which keeps the editor ignorant of what "tous" means.
 * Must match ChatMentionResolver::EVERYONE_TOKEN on the server.
 */
const EVERYONE_MEMBER = { user_id: 'tous', username: 'tous', hint: 'tout le groupe' }

const suggestionSource = computed(() => [EVERYONE_MEMBER, ...props.members])

async function send() {
  const message = content.value
  if (message.trim() === '' || props.isSending) {
    return
  }

  sendError.value = ''

  try {
    await props.sendMessage(message)
    content.value = ''
    emit('sent')
    nextTick(() => composer.value?.focus())
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
