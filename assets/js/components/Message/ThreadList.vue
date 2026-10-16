<template>
  <div class="h-full flex flex-col">
    <template v-if="messageStore.orderedThreads.length">
      <div class="flex-1 overflow-y-auto">
        <div
          v-for="threadMeta in messageStore.orderedThreads"
          :key="threadMeta.thread.id"
          class="p-4 border-b border-surface-200 dark:border-surface-700 cursor-pointer transition-colors"
          :class="{
            'bg-primary-50 dark:bg-primary-900/20': threadMeta.unread_count > 0,
            'bg-surface-100 dark:bg-surface-800': messageStore.currentThreadId === threadMeta.thread.id,
            'hover:bg-surface-50 dark:hover:bg-surface-800/50': messageStore.currentThreadId !== threadMeta.thread.id
          }"
          @click="messageStore.selectThread(threadMeta)"
        >
          <div class="flex items-start gap-3">
            <Avatar
              v-if="isChannel(threadMeta)"
              icon="pi pi-users"
              :style="getAvatarStyle(getTitle(threadMeta))"
              shape="circle"
              size="large"
              role="img"
              :aria-label="t('message.avatar.channel', { name: getTitle(threadMeta) })"
            />
            <Avatar
              v-else-if="getParticipant(threadMeta)?.profile_picture?.small && !getParticipant(threadMeta)?.deletion_datetime"
              :image="getParticipant(threadMeta).profile_picture.small"
              :pt="{ image: { alt: t('message.avatar.photo', { name: getTitle(threadMeta) }) } }"
              shape="circle"
              size="large"
              role="img"
              :aria-label="t('message.avatar.photo', { name: getTitle(threadMeta) })"
            />
            <Avatar
              v-else
              :label="getTitle(threadMeta).charAt(0).toUpperCase()"
              :style="getAvatarStyle(getParticipant(threadMeta)?.username ?? getTitle(threadMeta))"
              shape="circle"
              size="large"
              role="img"
              :aria-label="t('message.avatar.initial', { name: getTitle(threadMeta) })"
            />

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <template v-if="isChannel(threadMeta)">
                  <span class="font-semibold text-surface-900 dark:text-surface-0 truncate">
                    {{ getTitle(threadMeta) }}
                  </span>
                  <span class="text-xs text-surface-600 dark:text-surface-400 shrink-0">
                    #{{ threadMeta.thread.channel_name }}
                  </span>
                </template>
                <UserName
                  v-else-if="getParticipant(threadMeta)?.username && !getParticipant(threadMeta)?.deletion_datetime"
                  :username="getParticipant(threadMeta).username"
                  :display-name="getTitle(threadMeta)"
                  :to="{ name: 'app_user_public_profile', params: { username: getParticipant(threadMeta).username } }"
                  class="font-semibold text-surface-900 dark:text-surface-0 truncate hover:text-primary transition-colors"
                  @click.stop
                />
                <span v-else class="font-semibold text-surface-500 truncate">
                  {{ getTitle(threadMeta) }}
                </span>
                <Tag
                  v-if="threadMeta.unread_count > 0"
                  severity="info"
                  :value="unreadLabel(threadMeta.unread_count)"
                  :aria-label="t('message.thread_list.unread_aria_label', { count: threadMeta.unread_count })"
                  class="text-xs"
                />
              </div>

              <div class="text-xs text-surface-600 dark:text-surface-400 mb-1">
                {{ relativeDate(threadMeta.thread.last_message?.creation_datetime) }}
              </div>

              <ContactOrigin
                v-if="threadMeta.thread.latest_contact_origin"
                :origin="threadMeta.thread.latest_contact_origin"
                compact
                class="mb-1"
              />

              <p class="text-sm text-surface-600 dark:text-surface-300 truncate">
                <!-- Who wrote it, for a channel only: a direct message has one possible other author
                     and the row is already named after them. -->
                <span v-if="isChannel(threadMeta) && threadMeta.thread.last_message?.author">
                  {{ t('message.thread_list.last_message_author', { author: displayName(threadMeta.thread.last_message.author) }) }}
                </span>
                {{ threadMeta.thread.last_message?.content_preview || t('message.thread_list.no_message') }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="flex-1 flex items-center justify-center text-surface-500 dark:text-surface-400 p-4 text-center">
      {{ t('message.thread_list.empty') }}
    </div>
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Tag from 'primevue/tag'
import { useI18n } from 'vue-i18n'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { useMessageStore } from '../../store/message/message.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { conversationTitle, isChannel } from '../../utils/conversationIdentity.js'
import UserName from '../User/UserName.vue'
import ContactOrigin from './ContactOrigin.vue'

const { t } = useI18n()
const messageStore = useMessageStore()

function getParticipant(threadMeta) {
  return messageStore.getOtherParticipant(threadMeta)
}

function getTitle(threadMeta) {
  return conversationTitle(threadMeta, getParticipant(threadMeta))
}

// Capped like NotificationBell's badge, so a long-abandoned thread cannot stretch the row.
function unreadLabel(count) {
  return count > 99 ? '99+' : String(count)
}
</script>
