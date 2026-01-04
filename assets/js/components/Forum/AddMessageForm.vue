<template>
  <div>
    <template v-if="!userSecurityStore.isAuthenticated">
      <Card>
        <template #content>
          <div class="text-center py-4">
            <p class="mb-4">Vous devez vous connecter pour poster un message.</p>
            <Button label="Se connecter" icon="pi pi-sign-in" @click="handleLogin" />
          </div>
        </template>
      </Card>
    </template>

    <template v-else-if="isLocked">
      <Card>
        <template #content>
          <div class="text-center py-4 text-surface-500">
            <i class="pi pi-lock text-2xl mb-2" />
            <p>Ce sujet est verrouillé. Vous ne pouvez plus y répondre.</p>
          </div>
        </template>
      </Card>
    </template>

    <template v-else>
      <MessageEditor ref="editorRef" @content-update="handleContentUpdate" />

      <div class="flex justify-end mt-4">
        <Button
          label="Envoyer"
          icon="pi pi-send"
          :disabled="!canSubmit"
          :loading="isSending"
          @click="handleSubmit"
        />
      </div>
    </template>

    <AuthRequiredModal
      v-model:visible="showAuthModal"
      message="Vous devez vous connecter pour poster un message."
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Card from 'primevue/card'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useForumStore } from '../../store/forum/forum.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import AuthRequiredModal from '../Auth/AuthRequiredModal.vue'
import MessageEditor from './MessageEditor.vue'

const MIN_MESSAGE_LENGTH = 10

const props = defineProps({
  topicSlug: {
    type: String,
    required: true
  },
  isLocked: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['message-created'])

const forumStore = useForumStore()
const userSecurityStore = useUserSecurityStore()
const toast = useToast()

const contentHtml = ref('')
const contentText = ref('')
const isSending = ref(false)
const editorRef = ref(null)
const showAuthModal = ref(false)

const canSubmit = computed(() => {
  return contentText.value.trim().length >= MIN_MESSAGE_LENGTH
})

function handleContentUpdate({ html, text }) {
  contentHtml.value = html
  contentText.value = text
}

function handleLogin() {
  showAuthModal.value = true
}

async function handleSubmit() {
  if (!canSubmit.value) return

  isSending.value = true
  try {
    const post = await forumStore.createPost({
      topicSlug: props.topicSlug,
      content: contentHtml.value
    })
    editorRef.value?.reset()
    contentHtml.value = ''
    contentText.value = ''
    toast.add({
      severity: 'success',
      summary: 'Message envoyé',
      detail: 'Votre message a été publié avec succès',
      life: 3000
    })
    emit('message-created', post.id)
  } catch (error) {
    console.error('Failed to create post:', error)
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Une erreur est survenue lors de l'envoi du message",
      life: 5000
    })
  } finally {
    isSending.value = false
  }
}
</script>
