<template>
  <div v-if="userSecurityStore.isAuthenticated" class="flex gap-4">
    <div class="shrink-0">
      <Avatar
        v-if="userSecurityStore.profilePictureUrl"
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
  </div>

  <i18n-t
    v-else-if="parentId === null"
    keypath="publication.comment.form.login_required"
    tag="div"
    class="text-center py-4 text-surface-600 dark:text-surface-400"
  >
    <template #login>
      <RouterLink :to="{ name: 'app_login' }" class="text-primary font-medium">
        {{ t('publication.comment.form.login_link') }}
      </RouterLink>
    </template>
    <template #register>
      <RouterLink :to="{ name: 'app_register' }" class="text-primary font-medium">
        {{ t('publication.comment.form.register_link') }}
      </RouterLink>
    </template>
  </i18n-t>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCommentStore } from '../../store/comment/comment.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'

const props = defineProps({
  parentId: { type: Number, default: null },
  placeholder: { type: String, default: null },
  autofocus: { type: Boolean, default: false }
})

const emit = defineEmits(['posted', 'cancel'])

const { t } = useI18n()
const userSecurityStore = useUserSecurityStore()
const commentStore = useCommentStore()

const content = ref('')
const error = ref('')
const textareaRef = ref(null)

const canPost = computed(() => content.value.trim().length > 0 && !commentStore.isPosting)

onMounted(() => {
  if (props.autofocus) {
    nextTick(() => {
      textareaRef.value?.$el?.focus()
    })
  }
})

async function handleSubmit() {
  error.value = ''

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
