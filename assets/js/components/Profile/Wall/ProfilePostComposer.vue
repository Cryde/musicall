<template>
  <form class="flex gap-3" @submit.prevent="publish">
    <Avatar
      v-if="pictureUrl"
      :image="pictureUrl"
      :pt="{ image: { alt: '' } }"
      shape="circle"
      class="w-10! h-10! shrink-0"
    />
    <Avatar
      v-else
      :label="displayName.charAt(0).toUpperCase()"
      :style="getAvatarStyle(username)"
      shape="circle"
      class="w-10! h-10! shrink-0"
    />
    <div class="flex-1 min-w-0 flex flex-col gap-2">
      <Textarea
        ref="textarea"
        v-model="draft.content"
        :aria-label="t('profile.wall.composer_aria')"
        :placeholder="t('profile.wall.composer_placeholder', { name: displayName })"
        :maxlength="MAX_POST_LENGTH"
        :disabled="isPublishing"
        auto-resize
        rows="2"
        class="w-full"
      />

      <ul v-if="previews.length" class="list-none m-0 p-0 flex flex-wrap gap-2" :aria-label="t('profile.wall.photos_selected')">
        <li v-for="(preview, index) in previews" :key="preview.url" class="relative">
          <img :src="preview.url" :alt="preview.name" class="w-20 h-20 object-cover rounded-lg" />
          <Button
            icon="pi pi-times"
            rounded
            size="small"
            severity="secondary"
            class="absolute! -top-2 -right-2 w-6! h-6!"
            :aria-label="t('profile.wall.remove_photo', { name: preview.name })"
            :disabled="isPublishing"
            @click="removePhoto(index)"
          />
        </li>
      </ul>

      <InputText
        v-if="isLinkOpen"
        v-model="draft.mediaUrl"
        type="url"
        :aria-label="t('profile.wall.link_aria')"
        :placeholder="t('profile.wall.link_placeholder')"
        :disabled="isPublishing"
        maxlength="500"
      />

      <Message v-if="error" severity="error" size="small" :closable="false">{{ error }}</Message>

      <div class="flex flex-wrap items-center gap-2">
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          :accept="ACCEPTED_IMAGE_TYPES.join(',')"
          multiple
          @change="handleFiles"
        />
        <Button
          icon="pi pi-image"
          :label="t('profile.wall.add_photo')"
          size="small"
          severity="secondary"
          :disabled="isPublishing || isLinkOpen || draft.photos.length >= MAX_POST_PHOTOS"
          @click="fileInput.click()"
        />
        <Button
          icon="pi pi-youtube"
          :label="t('profile.wall.add_link')"
          size="small"
          severity="secondary"
          :disabled="isPublishing || draft.photos.length > 0"
          :aria-pressed="isLinkOpen"
          @click="toggleLink"
        />
        <Button
          type="submit"
          :label="t('profile.wall.publish')"
          size="small"
          class="ml-auto"
          :loading="isPublishing"
          :disabled="!canPublish(draft)"
        />
      </div>
    </div>
  </form>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileWallApi from '../../../api/user/profileWall.js'
import { getAvatarStyle } from '../../../utils/avatar.js'
import { ACCEPTED_IMAGE_TYPES, imageRefusalFor } from '../../../utils/chatImageDraft.js'
import {
  canPublish,
  emptyPostDraft,
  MAX_POST_LENGTH,
  MAX_POST_PHOTOS,
  postPayload
} from '../../../utils/profileWall.js'

defineProps({
  username: { type: String, required: true },
  displayName: { type: String, required: true },
  pictureUrl: { type: String, default: null }
})

const emit = defineEmits(['created'])
const { t } = useI18n()

const draft = ref(emptyPostDraft())
const previews = ref([])
const isLinkOpen = ref(false)
const isPublishing = ref(false)
const error = ref('')
const fileInput = ref(null)
const textarea = ref(null)

function handleFiles(event) {
  error.value = ''
  for (const file of Array.from(event.target.files ?? [])) {
    if (draft.value.photos.length >= MAX_POST_PHOTOS) {
      error.value = t('profile.wall.too_many_photos', { max: MAX_POST_PHOTOS })
      break
    }
    const refusal = imageRefusalFor(file)
    if (refusal) {
      error.value = refusal
      continue
    }
    draft.value.photos.push(file)
    previews.value.push({ name: file.name, url: URL.createObjectURL(file) })
  }
  // The same file can be picked again after it was removed.
  event.target.value = ''
}

function removePhoto(index) {
  URL.revokeObjectURL(previews.value[index].url)
  previews.value.splice(index, 1)
  draft.value.photos.splice(index, 1)
}

function toggleLink() {
  isLinkOpen.value = !isLinkOpen.value
  if (!isLinkOpen.value) {
    draft.value.mediaUrl = ''
  }
}

function reset() {
  for (const preview of previews.value) {
    URL.revokeObjectURL(preview.url)
  }
  previews.value = []
  draft.value = emptyPostDraft()
  isLinkOpen.value = false
}

async function publish() {
  if (!canPublish(draft.value)) return
  isPublishing.value = true
  error.value = ''
  try {
    const post = await profileWallApi.createPost(postPayload(draft.value))
    reset()
    emit('created', post)
  } catch (e) {
    error.value = e.status === 429 ? t('profile.wall.too_many_posts') : e.message
  } finally {
    isPublishing.value = false
  }
}

onUnmounted(reset)

// The owner's checklist (« Ton premier post ») brings the wall into view first.
defineExpose({ focus: () => textarea.value?.$el?.focus({ preventScroll: true }) })
</script>
