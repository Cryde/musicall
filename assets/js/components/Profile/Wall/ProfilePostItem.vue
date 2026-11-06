<template>
  <article class="p-4 flex flex-col gap-3" :aria-labelledby="`profile-post-${post.id}`">
    <p v-if="post.is_pinned" class="flex items-center gap-1 text-xs font-medium text-surface-500 dark:text-surface-400 m-0">
      <i class="pi pi-thumbtack text-xs" aria-hidden="true" />{{ t('profile.wall.pinned') }}
    </p>

    <header class="flex items-center gap-3">
      <Avatar
        v-if="post.author.profile_picture_url"
        :image="post.author.profile_picture_url"
        :pt="{ image: { alt: '' } }"
        shape="circle"
        class="w-10! h-10! shrink-0"
      />
      <Avatar
        v-else
        :label="post.author.display_name.charAt(0).toUpperCase()"
        :style="getAvatarStyle(post.author.username)"
        shape="circle"
        class="w-10! h-10! shrink-0"
      />
      <div class="flex-1 min-w-0">
        <p :id="`profile-post-${post.id}`" class="font-semibold text-surface-900 dark:text-surface-0 m-0 truncate">
          {{ post.author.display_name }}
        </p>
        <p class="text-xs text-surface-500 dark:text-surface-400 m-0">
          <time :datetime="post.creation_datetime">{{ formatDateCompactWithYear(post.creation_datetime) }}</time>
          <template v-if="post.update_datetime">, {{ t('profile.wall.edited') }}</template>
        </p>
      </div>
      <template v-if="post.can_edit">
        <Button
          icon="pi pi-ellipsis-h"
          text
          rounded
          severity="secondary"
          aria-haspopup="true"
          :aria-controls="`profile-post-menu-${post.id}`"
          :aria-expanded="isMenuOpen"
          :aria-label="t('profile.wall.actions')"
          @click="menu.toggle($event)"
        />
        <Menu
          :id="`profile-post-menu-${post.id}`"
          ref="menu"
          :model="menuItems"
          popup
          @show="isMenuOpen = true"
          @hide="isMenuOpen = false"
        />
      </template>
      <ReportButton
        v-else
        target-type="profile_post"
        :target-id="post.id"
        :author-username="post.author.username"
        icon-only
      />
    </header>

    <form v-if="isEditing" class="flex flex-col gap-2" @submit.prevent="saveEdit">
      <Textarea
        v-model="editedContent"
        :aria-label="t('profile.wall.edit_aria')"
        :maxlength="MAX_POST_LENGTH"
        :disabled="isSaving"
        auto-resize
        rows="3"
      />
      <Message v-if="error" severity="error" size="small" :closable="false">{{ error }}</Message>
      <div class="flex justify-end gap-2">
        <Button :label="t('profile.wall.cancel')" size="small" severity="secondary" text :disabled="isSaving" @click="isEditing = false" />
        <Button type="submit" :label="t('profile.wall.save')" size="small" :loading="isSaving" />
      </div>
    </form>
    <p v-else-if="post.content" class="text-surface-800 dark:text-surface-100 whitespace-pre-line break-words m-0">
      {{ post.content }}
    </p>

    <div v-if="post.photos.length" class="grid gap-1 rounded-xl overflow-hidden" :class="photoGridClass(post.photos.length)">
      <Image
        v-for="(photo, index) in post.photos"
        :key="photo.id"
        :src="photo.url"
        :alt="t('profile.wall.photo_alt', { number: index + 1, name: post.author.display_name })"
        preview
        class="block h-full"
        :class="post.photos.length === 3 && index === 0 ? 'row-span-2' : ''"
        :pt="{
          image: {
            class: post.photos.length === 1 ? 'w-full max-h-[32rem] object-cover' : 'w-full h-full object-cover',
            loading: 'lazy'
          }
        }"
      />
    </div>

    <button
      v-if="post.media"
      type="button"
      class="group relative block w-full rounded-xl overflow-hidden bg-surface-900 text-left cursor-pointer"
      :aria-label="t('profile.wall.play', { title: post.media.title || post.media.url })"
      @click="isPlaying = true"
    >
      <img
        v-if="post.media.platform === 'youtube'"
        :src="youtubeThumbnailUrl(post.media.embed_id)"
        alt=""
        class="w-full aspect-video object-cover opacity-90 group-hover:opacity-100"
        loading="lazy"
      />
      <span v-else class="flex items-center gap-3 p-4 text-surface-0">
        <i :class="post.media.platform === 'spotify' ? 'pi pi-play-circle' : 'pi pi-volume-up'" class="text-2xl" aria-hidden="true" />
        <span class="font-medium truncate">{{ post.media.title || post.media.url }}</span>
      </span>
      <span
        v-if="post.media.platform === 'youtube'"
        class="absolute inset-0 flex items-center justify-center"
        aria-hidden="true"
      >
        <span class="w-14 h-14 rounded-full bg-black/60 flex items-center justify-center">
          <i class="pi pi-play text-white text-xl" />
        </span>
      </span>
    </button>
    <MediaPlayerDialog v-model:visible="isPlaying" :media="post.media" />
  </article>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Image from 'primevue/image'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileWallApi from '../../../api/user/profileWall.js'
import { getAvatarStyle } from '../../../utils/avatar.js'
import { formatDateCompactWithYear } from '../../../utils/date.js'
import { MAX_POST_LENGTH, photoGridClass, youtubeThumbnailUrl } from '../../../utils/profileWall.js'
import MediaPlayerDialog from '../../Media/MediaPlayerDialog.vue'
import ReportButton from '../../Report/ReportButton.vue'

const props = defineProps({
  post: { type: Object, required: true }
})

const emit = defineEmits(['updated', 'deleted'])
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()

const menu = ref(null)
const isMenuOpen = ref(false)
const isEditing = ref(false)
const isSaving = ref(false)
const isPlaying = ref(false)
const editedContent = ref('')
const error = ref('')

const menuItems = computed(() => [
  { label: t('profile.wall.edit'), icon: 'pi pi-pencil', command: startEdit },
  {
    label: props.post.is_pinned ? t('profile.wall.unpin') : t('profile.wall.pin'),
    icon: 'pi pi-thumbtack',
    command: togglePin
  },
  { label: t('profile.wall.delete'), icon: 'pi pi-trash', command: askDelete }
])

function startEdit() {
  editedContent.value = props.post.content ?? ''
  error.value = ''
  isEditing.value = true
}

async function saveEdit() {
  isSaving.value = true
  error.value = ''
  try {
    emit(
      'updated',
      await profileWallApi.updatePost(props.post.id, { content: editedContent.value })
    )
    isEditing.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    isSaving.value = false
  }
}

async function togglePin() {
  try {
    emit(
      'updated',
      await profileWallApi.updatePost(props.post.id, { is_pinned: !props.post.is_pinned })
    )
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('profile.wall.error'),
      detail: e.message,
      life: 5000
    })
  }
}

function askDelete() {
  confirm.require({
    header: t('profile.wall.delete_title'),
    message: t('profile.wall.delete_confirm'),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('profile.wall.cancel'), severity: 'secondary', outlined: true },
    acceptProps: { label: t('profile.wall.delete'), severity: 'danger' },
    accept: deletePost
  })
}

async function deletePost() {
  try {
    await profileWallApi.deletePost(props.post.id)
    emit('deleted', props.post.id)
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('profile.wall.error'),
      detail: e.message,
      life: 5000
    })
  }
}
</script>
