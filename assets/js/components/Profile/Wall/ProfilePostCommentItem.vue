<template>
  <article class="flex gap-2" :aria-labelledby="`profile-comment-${comment.id}`">
    <span
      v-if="isDeleted"
      class="w-8 h-8 shrink-0 rounded-full bg-surface-200 dark:bg-surface-700 flex items-center justify-center"
      aria-hidden="true"
    >
      <i class="pi pi-user text-surface-500 text-sm" />
    </span>
    <Avatar
      v-else-if="comment.author.profile_picture_url"
      :image="comment.author.profile_picture_url"
      :pt="{ image: { alt: '' } }"
      shape="circle"
      class="w-8! h-8! shrink-0"
    />
    <Avatar
      v-else
      :label="comment.author.display_name.charAt(0).toUpperCase()"
      :style="getAvatarStyle(comment.author.username)"
      shape="circle"
      class="w-8! h-8! shrink-0"
    />
    <div class="flex-1 min-w-0">
      <div class="rounded-2xl bg-surface-100 dark:bg-surface-800 px-3 py-2">
        <div class="flex items-start gap-2">
          <p :id="`profile-comment-${comment.id}`" class="flex-1 min-w-0 text-sm font-semibold m-0 truncate">
            <span v-if="isDeleted" class="text-surface-500 dark:text-surface-400">{{ t('shared.user.deleted') }}</span>
            <RouterLink
              v-else
              :to="{ name: 'app_user_public_profile', params: { username: comment.author.username } }"
              class="text-surface-900 dark:text-surface-0 hover:underline"
            >
              {{ comment.author.display_name }}
            </RouterLink>
          </p>
          <template v-if="comment.can_delete">
            <Button
              icon="pi pi-ellipsis-h"
              text
              rounded
              size="small"
              severity="secondary"
              class="-my-1 -mr-2 w-7! h-7!"
              aria-haspopup="true"
              :aria-controls="`profile-comment-menu-${comment.id}`"
              :aria-expanded="isMenuOpen"
              :aria-label="t('profile.wall.comment_actions')"
              @click="menu.toggle($event)"
            />
            <Menu
              :id="`profile-comment-menu-${comment.id}`"
              ref="menu"
              :model="menuItems"
              popup
              @show="isMenuOpen = true"
              @hide="isMenuOpen = false"
            />
          </template>
          <ReportButton
            v-else-if="!isDeleted"
            target-type="profile_post_comment"
            :target-id="comment.id"
            :author-username="comment.author.username"
            icon-only
            class="-my-1 -mr-2"
          />
        </div>
        <p class="text-sm text-surface-800 dark:text-surface-100 whitespace-pre-line break-words m-0">{{ comment.content }}</p>
      </div>
      <p class="flex items-center gap-3 text-xs text-surface-500 dark:text-surface-400 mt-1 ml-3 mb-0">
        <time :datetime="comment.creation_datetime">{{ formatDateCompactWithYear(comment.creation_datetime) }}</time>
        <button
          v-if="canReply"
          type="button"
          class="font-semibold hover:underline cursor-pointer"
          @click="emit('reply', comment)"
        >
          {{ t('profile.wall.reply') }}
        </button>
      </p>
    </div>
  </article>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { useConfirm } from 'primevue/useconfirm'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAvatarStyle } from '../../../utils/avatar.js'
import { formatDateCompactWithYear } from '../../../utils/date.js'
import ReportButton from '../../Report/ReportButton.vue'

const props = defineProps({
  comment: { type: Object, required: true },
  /** Only a comment, not a reply, can be answered: one level. */
  canReply: { type: Boolean, default: false }
})

const emit = defineEmits(['reply', 'delete'])
const { t } = useI18n()
const confirm = useConfirm()

const menu = ref(null)
const isMenuOpen = ref(false)
const isDeleted = computed(() => Boolean(props.comment.author.deletion_datetime))

const menuItems = computed(() => [
  { label: t('profile.wall.delete'), icon: 'pi pi-trash', command: askDelete }
])

function askDelete() {
  confirm.require({
    header: t('profile.wall.delete_comment_title'),
    message: props.comment.parent_id
      ? t('profile.wall.delete_reply_confirm')
      : t('profile.wall.delete_comment_confirm'),
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: t('profile.wall.cancel'), severity: 'secondary', outlined: true },
    acceptProps: { label: t('profile.wall.delete'), severity: 'danger' },
    accept: () => emit('delete', props.comment)
  })
}
</script>
