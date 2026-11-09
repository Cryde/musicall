<template>
  <div>
    <template v-if="isLocked">
      <Card>
        <template #content>
          <div class="text-center py-4 text-surface-500">
            <i class="pi pi-lock text-2xl mb-2" />
            <p>{{ t('forum.reply.locked') }}</p>
          </div>
        </template>
      </Card>
    </template>

    <!-- A visitor writes too (#1305): « Envoyer » asks them to sign in, keeping what they wrote. -->
    <template v-else>
      <MessageEditor
        ref="editorRef"
        :previous-content="restoredHtml"
        @content-update="handleContentUpdate"
        @sign-in-required="emitSignInRequired('image')"
      />

      <div class="flex justify-end mt-4">
        <Button
          :label="t('forum.reply.send')"
          icon="pi pi-send"
          :disabled="!canSubmit"
          :loading="isSending"
          @click="handleSubmit"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useForumStore } from '../../store/forum/forum.js'
import { useUserSecurityStore } from '../../store/user/security.js'
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

// `sign-in-required` carries what a visitor wrote when they click « Envoyer » or pick an image: nothing is sent.
const emit = defineEmits(['message-created', 'sign-in-required'])

const { t } = useI18n()
const forumStore = useForumStore()
const userSecurityStore = useUserSecurityStore()
const toast = useToast()

const contentHtml = ref('')
const contentText = ref('')
const isSending = ref(false)
const editorRef = ref(null)
// A reply brought back after sign-in, handed to the editor (#1305).
const restoredHtml = ref('')

// Not while the session is checked: a visitor's click would reach the API, and its 401 would lose the text.
const canSubmit = computed(() => {
  return (
    !userSecurityStore.isAuthenticatedLoading &&
    contentText.value.trim().length >= MIN_MESSAGE_LENGTH
  )
})

function handleContentUpdate({ html, text }) {
  contentHtml.value = html
  contentText.value = text
}

function insertQuote(quote) {
  editorRef.value?.insertQuote(quote)
}

/** Puts back a reply written before signing in. */
function restore(html) {
  restoredHtml.value = html
}

defineExpose({ insertQuote, restore })

/** @param {'publish' | 'image'} reason  what the visitor was doing when stopped */
function emitSignInRequired(reason) {
  emit('sign-in-required', { html: contentHtml.value, reason })
}

async function handleSubmit() {
  if (!canSubmit.value) return
  if (!userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated) {
    emitSignInRequired('publish')
    return
  }

  isSending.value = true
  try {
    const post = await forumStore.createPost({
      topicSlug: props.topicSlug,
      content: contentHtml.value
    })
    trackUmamiEvent('forum-reply-submit', { topic: props.topicSlug })
    editorRef.value?.reset()
    restoredHtml.value = ''
    contentHtml.value = ''
    contentText.value = ''
    toast.add({
      severity: 'success',
      summary: t('forum.reply.sent_toast'),
      detail: t('forum.reply.sent_toast_detail'),
      life: 3000
    })
    emit('message-created', post.id)
  } catch (error) {
    console.error('Failed to create post:', error)
    toast.add({
      severity: 'error',
      summary: t('forum.common.error'),
      detail:
        error.response?.status === 429
          ? t('forum.reply.rate_limited')
          : t('forum.reply.send_failed'),
      life: 5000
    })
  } finally {
    isSending.value = false
  }
}
</script>
