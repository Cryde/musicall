<template>
  <!-- A visitor writes a comment too (#1305): « Poster le commentaire » asks them to sign in, keeping it. -->
  <div v-if="userSecurityStore.isAuthenticated || parentId === null" ref="formRef" class="flex gap-4">
    <div class="shrink-0">
      <Avatar
        v-if="!userSecurityStore.isAuthenticated"
        icon="pi pi-user"
        shape="circle"
        :size="parentId === null ? 'large' : 'normal'"
      />
      <Avatar
        v-else-if="userSecurityStore.profilePictureUrl"
        :image="userSecurityStore.profilePictureUrl"
        shape="circle"
        :size="parentId === null ? 'large' : 'normal'"
      />
      <Avatar
        v-else
        :label="userSecurityStore.user?.username?.charAt(0).toUpperCase()"
        :style="getAvatarStyle(userSecurityStore.user?.username)"
        shape="circle"
        :size="parentId === null ? 'large' : 'normal'"
      />
    </div>
    <div class="flex-1 flex flex-col gap-3">
      <Message v-if="error" severity="error" :closable="false">
        {{ error }}
      </Message>

      <Textarea
        ref="textareaRef"
        v-model="content"
        :placeholder="placeholder ?? t('publication.comment.form.placeholder')"
        :aria-label="t('publication.comment.form.aria_label')"
        :rows="parentId === null ? 3 : 2"
        :disabled="commentStore.isPosting"
        class="w-full"
      />

      <div class="flex justify-end gap-2">
        <Button
          v-if="parentId !== null"
          :label="t('publication.common.cancel')"
          severity="secondary"
          text
          :disabled="commentStore.isPosting"
          @click="$emit('cancel')"
        />
        <Button
          :label="parentId === null ? t('publication.comment.form.post') : t('publication.comment.reply')"
          icon="pi pi-send"
          :loading="commentStore.isPosting"
          :disabled="!canPost"
          @click="handleSubmit"
        />
      </div>
    </div>

    <AuthRequiredModal
      v-if="showAuthModal"
      v-model:visible="showAuthModal"
      :message="isDraftKept ? t('publication.pending.comment_sign_in') : ''"
    />
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, defineAsyncComponent, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useKeepCommentDraft } from '../../composables/useCommentDraft.js'
import { useCommentStore } from '../../store/comment/comment.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'

// Only a visitor ever sees it, so it stays out of the bundle until then.
const AuthRequiredModal = defineAsyncComponent(() => import('../Auth/AuthRequiredModal.vue'))

const props = defineProps({
  parentId: { type: Number, default: null },
  placeholder: { type: String, default: null },
  autofocus: { type: Boolean, default: false }
})

const emit = defineEmits(['posted', 'cancel'])

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const commentStore = useCommentStore()
const keepCommentDraft = useKeepCommentDraft()

const content = ref('')
const error = ref('')
const formRef = ref(null)
const textareaRef = ref(null)
const showAuthModal = ref(false)
const isDraftKept = ref(false)

// Not while the session is checked: a visitor's click would reach the API, and its 401 would lose the text.
const canPost = computed(
  () =>
    !userSecurityStore.isAuthenticatedLoading &&
    content.value.trim().length > 0 &&
    !commentStore.isPosting
)

onMounted(() => {
  if (props.autofocus) {
    nextTick(() => {
      textareaRef.value?.$el?.focus()
    })
  }
})

// A comment brought back after sign-in goes in the page's own form, which is then brought into view.
watch(
  () => commentStore.restoredDraft,
  async (draft) => {
    if (draft === null || props.parentId !== null) return
    content.value = commentStore.takeRestoredDraft()
    await nextTick()
    formRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    textareaRef.value?.$el?.focus({ preventScroll: true })
  },
  { immediate: true }
)

async function handleSubmit() {
  error.value = ''
  if (!userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated) {
    // Promised only when kept: a browser that blocks storage gets the usual prompt.
    isDraftKept.value = keepCommentDraft({
      threadId: commentStore.threadId,
      content: content.value
    })
    showAuthModal.value = true
    return
  }

  try {
    await commentStore.postComment({
      content: content.value,
      parentId: props.parentId
    })
    trackUmamiEvent(props.parentId === null ? 'comment-add' : 'comment-reply')
    content.value = ''
    emit('posted')
  } catch (e) {
    // handleApiError normalises errors into { status, message, violations, ... };
    // e.message already carries the API's `detail` for non-validation 4xx responses.
    if (e.status === 429) {
      error.value = t('publication.comment.form.rate_limited')
    } else {
      error.value = e.message || t('publication.comment.form.error')
    }
  }
}
</script>
