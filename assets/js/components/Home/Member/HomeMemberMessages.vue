<template>
  <HomeMemberPanel title="Messages" link-label="Tous les messages" :link-to="{ name: 'app_messages' }">
    <template #aside>
      <span v-if="notificationStore.unreadMessages > 0" class="text-sm font-bold text-orange-700 dark:text-orange-300">
        {{ notificationStore.unreadMessages }} non lu{{ notificationStore.unreadMessages > 1 ? 's' : '' }}
      </span>
    </template>

    <div v-if="!messageStore.hasLoadedThreads" class="flex flex-col gap-3" aria-busy="true">
      <div v-for="i in 2" :key="i" class="h-12 rounded-lg bg-surface-100 dark:bg-surface-800 animate-pulse" />
    </div>
    <ul v-else-if="threads.length > 0" class="m-0 p-0 list-none flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
      <li v-for="threadMeta in threads" :key="threadMeta.id">
        <router-link
          :to="{ name: 'app_messages', params: { threadId: threadMeta.thread.id } }"
          class="flex items-center gap-3 py-2.5 rounded-md hover:bg-surface-50 dark:hover:bg-surface-800/60"
        >
          <Avatar :label="initialOf(threadMeta)" :style="getAvatarStyle(nameOf(threadMeta))" shape="circle" aria-hidden="true" />
          <span class="flex flex-col min-w-0 flex-1">
            <span class="text-sm truncate text-surface-900 dark:text-surface-0" :class="threadMeta.unread_count > 0 ? 'font-bold' : 'font-medium'">
              {{ nameOf(threadMeta) }}
            </span>
            <span class="text-sm truncate text-surface-600 dark:text-surface-300">{{ threadMeta.thread.last_message?.content_preview }}</span>
          </span>
          <span v-if="threadMeta.unread_count > 0" class="w-2 h-2 shrink-0 rounded-full bg-orange-500" aria-hidden="true" />
          <span v-if="threadMeta.unread_count > 0" class="sr-only">Non lu</span>
        </router-link>
      </li>
    </ul>
    <p v-else class="m-0 text-sm text-surface-600 dark:text-surface-300">Pas encore de conversation.</p>
  </HomeMemberPanel>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import { computed, onMounted } from 'vue'
import { useMessageStore } from '../../../store/message/message.js'
import { useNotificationStore } from '../../../store/notification/notification.js'
import { getAvatarStyle } from '../../../utils/avatar.js'
import HomeMemberPanel from './HomeMemberPanel.vue'

/** The latest conversations (#1078); the unread total is the navbar's, kept live by the stream. */
const THREADS_SHOWN = 3

const messageStore = useMessageStore()
const notificationStore = useNotificationStore()

const threads = computed(() => messageStore.orderedThreads.slice(0, THREADS_SHOWN))

onMounted(() => {
  messageStore.loadThreads({ silent: messageStore.hasLoadedThreads })
})

// A Band Space channel is named after its band, a conversation after the other person.
function nameOf(threadMeta) {
  if (threadMeta.thread.band_space_name) return threadMeta.thread.band_space_name
  return messageStore.getOtherParticipant(threadMeta)?.username ?? 'Conversation'
}

function initialOf(threadMeta) {
  return nameOf(threadMeta).charAt(0).toUpperCase()
}
</script>
