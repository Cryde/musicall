<template>
  <div class="h-full flex flex-col">
    <template v-if="messageStore.orderedThreads.length">
      <div class="flex-1 overflow-y-auto">
        <div
          v-for="threadMeta in messageStore.orderedThreads"
          :key="threadMeta.thread.id"
          class="p-4 border-b border-surface-200 dark:border-surface-700 cursor-pointer transition-colors"
          :class="{
            'bg-primary-50 dark:bg-primary-900/20': !threadMeta.is_read,
            'bg-surface-100 dark:bg-surface-800': messageStore.currentThreadId === threadMeta.thread.id,
            'hover:bg-surface-50 dark:hover:bg-surface-800/50': messageStore.currentThreadId !== threadMeta.thread.id
          }"
          @click="messageStore.selectThread(threadMeta)"
        >
          <div class="flex items-start gap-3">
            <Avatar
              v-if="getParticipant(threadMeta)?.profile_picture?.small && !getParticipant(threadMeta)?.deletion_datetime"
              :image="getParticipant(threadMeta).profile_picture.small"
              :pt="{ image: { alt: `Photo de ${getParticipantName(threadMeta)}` } }"
              shape="circle"
              size="large"
              role="img"
              :aria-label="`Photo de ${getParticipantName(threadMeta)}`"
            />
            <Avatar
              v-else
              :label="getParticipantName(threadMeta).charAt(0).toUpperCase()"
              :style="getAvatarStyle(getParticipantName(threadMeta))"
              shape="circle"
              size="large"
              role="img"
              :aria-label="`Avatar de ${getParticipantName(threadMeta)}`"
            />

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <router-link
                  v-if="getParticipant(threadMeta)?.username && !getParticipant(threadMeta)?.deletion_datetime"
                  :to="{ name: 'app_user_public_profile', params: { username: getParticipant(threadMeta).username } }"
                  class="font-semibold text-surface-900 dark:text-surface-0 truncate hover:text-primary transition-colors"
                  @click.stop
                >{{ getParticipantName(threadMeta) }}</router-link>
                <span v-else class="font-semibold text-surface-500 truncate">
                  {{ getParticipantName(threadMeta) }}
                </span>
                <Tag v-if="!threadMeta.is_read" severity="info" value="new" class="text-xs" />
              </div>

              <div class="text-xs text-surface-500 dark:text-surface-400 mb-1">
                {{ relativeDate(threadMeta.thread.last_message?.creation_datetime) }}
              </div>

              <p class="text-sm text-surface-600 dark:text-surface-300 truncate">
                {{ threadMeta.thread.last_message?.content || 'Aucun message' }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="flex-1 flex items-center justify-center text-surface-500 dark:text-surface-400 p-4 text-center">
      Vous n'avez pas encore de message.
    </div>
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Tag from 'primevue/tag'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { useMessageStore } from '../../store/message/message.js'
import { getAvatarStyle } from '../../utils/avatar.js'

const messageStore = useMessageStore()

function getParticipant(threadMeta) {
  return messageStore.getOtherParticipant(threadMeta)
}

function getParticipantName(threadMeta) {
  const participant = getParticipant(threadMeta)
  return participant ? displayName(participant) : 'Utilisateur inconnu'
}
</script>
