<template>
  <RouterLink
    :to="{ name: 'app_messages', params: { threadId: threadMeta.thread.id } }"
    class="flex gap-3 px-4 py-3 transition-colors duration-150"
    :class="isCurrent ? 'bg-surface-100 dark:bg-surface-800' : 'hover:bg-surface-50 dark:hover:bg-surface-800/50'"
  >
    <!-- The name says who it is, so the picture is not read out a second time. -->
    <Avatar
      v-if="isChannel(threadMeta)"
      icon="pi pi-users"
      :style="getAvatarStyle(title)"
      shape="circle"
      size="large"
      class="shrink-0"
      aria-hidden="true"
    />
    <Avatar
      v-else-if="participant?.profile_picture?.small && !participant?.deletion_datetime"
      :image="participant.profile_picture.small"
      :pt="{ image: { alt: '' } }"
      shape="circle"
      size="large"
      class="shrink-0"
      aria-hidden="true"
    />
    <Avatar
      v-else
      :label="title.charAt(0).toUpperCase()"
      :style="getAvatarStyle(participant?.username ?? title)"
      shape="circle"
      size="large"
      class="shrink-0"
      aria-hidden="true"
    />

    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
      <span class="flex items-baseline gap-2">
        <span
          class="truncate"
          :class="isUnread ? 'font-bold text-surface-900 dark:text-surface-0' : 'font-semibold text-surface-900 dark:text-surface-0'"
        >
          {{ title }}
        </span>
        <span v-if="isChannel(threadMeta)" class="shrink-0 text-xs text-surface-600 dark:text-surface-400">
          #{{ threadMeta.thread.channel_name }}
        </span>
        <span
          v-if="time"
          class="ml-auto shrink-0 text-xs tabular-nums"
          :class="isUnread ? 'font-semibold text-primary' : 'text-surface-600 dark:text-surface-400'"
        >
          {{ time }}
        </span>
      </span>

      <ContactOrigin v-if="!compact && threadMeta.thread.latest_contact_origin" :origin="threadMeta.thread.latest_contact_origin" compact />

      <span class="flex items-center gap-2">
        <span
          class="min-w-0 flex-1 truncate text-sm"
          :class="isUnread ? 'font-medium text-surface-900 dark:text-surface-0' : 'text-surface-600 dark:text-surface-300'"
        >
          {{ preview }}
        </span>
        <template v-if="isUnread">
          <Badge :value="threadMeta.unread_count > 99 ? '99+' : String(threadMeta.unread_count)" severity="danger" aria-hidden="true" />
          <span class="sr-only">{{ t('message.thread_list.unread_aria_label', { count: threadMeta.unread_count }) }}</span>
        </template>
      </span>
    </span>
  </RouterLink>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { displayName } from '../../helper/user/displayName.js'
import { useMessageStore } from '../../store/message/message.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { conversationTitle, isChannel } from '../../utils/conversationIdentity.js'
import { conversationTimeLabel } from '../../utils/conversationTime.js'
import ContactOrigin from './ContactOrigin.vue'

/**
 * One conversation, as the inbox and the envelope list it: who, when, and the last message, bold while
 * something is unread. The whole row opens it.
 */
const props = defineProps({
  threadMeta: { type: Object, required: true },
  // The envelope's short list leaves out where the contact came from.
  compact: { type: Boolean, default: false },
  isCurrent: { type: Boolean, default: false },
  // The clock of the list it sits in, so « 09:41 » becomes « Hier » on a page left open.
  now: { type: Date, default: () => new Date() }
})

const { t } = useI18n()
const messageStore = useMessageStore()

const participant = computed(() => messageStore.getOtherParticipant(props.threadMeta))
const title = computed(() => conversationTitle(props.threadMeta, participant.value))
const lastMessage = computed(() => props.threadMeta.thread.last_message)
const isUnread = computed(() => props.threadMeta.unread_count > 0)
const time = computed(() => conversationTimeLabel(lastMessage.value?.creation_datetime, props.now))

// Who wrote it, for a band only: a direct conversation has one other author, already the row's name.
const preview = computed(() => {
  const text = lastMessage.value?.content_preview || t('message.thread_list.no_message')
  if (!isChannel(props.threadMeta) || !lastMessage.value?.author) return text
  return `${t('message.thread_list.last_message_author', { author: displayName(lastMessage.value.author) })} ${text}`
})
</script>
