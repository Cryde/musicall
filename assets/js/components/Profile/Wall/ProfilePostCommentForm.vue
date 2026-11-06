<template>
  <form class="flex flex-col gap-2" @submit.prevent="submit">
    <Textarea
      ref="textarea"
      v-model="content"
      :aria-label="parentId ? t('profile.wall.reply_aria') : t('profile.wall.comment_aria')"
      :placeholder="parentId ? t('profile.wall.reply_placeholder') : t('profile.wall.comment_placeholder')"
      :maxlength="MAX_COMMENT_LENGTH"
      :disabled="isSending"
      auto-resize
      rows="1"
      class="w-full"
    />
    <Message v-if="error" severity="error" size="small" :closable="false">{{ error }}</Message>
    <div class="flex justify-end gap-2">
      <Button
        v-if="parentId"
        :label="t('profile.wall.cancel')"
        size="small"
        severity="secondary"
        text
        :disabled="isSending"
        @click="emit('cancel')"
      />
      <Button
        type="submit"
        :label="parentId ? t('profile.wall.reply') : t('profile.wall.publish')"
        size="small"
        :loading="isSending"
        :disabled="content.trim() === ''"
      />
    </div>
  </form>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileWallApi from '../../../api/user/profileWall.js'
import { MAX_COMMENT_LENGTH } from '../../../utils/profileWall.js'

const props = defineProps({
  postId: { type: String, required: true },
  parentId: { type: String, default: null },
  autofocus: { type: Boolean, default: false }
})

const emit = defineEmits(['posted', 'cancel'])
const { t } = useI18n()

const textarea = ref(null)
const content = ref('')
const isSending = ref(false)
const error = ref('')

onMounted(() => {
  if (props.autofocus) {
    textarea.value?.$el.focus()
  }
})

async function submit() {
  if (content.value.trim() === '') return
  isSending.value = true
  error.value = ''
  try {
    emit('posted', await profileWallApi.createComment(props.postId, content.value, props.parentId))
    content.value = ''
  } catch (e) {
    error.value = e.status === 429 ? t('profile.wall.too_many_comments') : e.message
  } finally {
    isSending.value = false
  }
}
</script>
