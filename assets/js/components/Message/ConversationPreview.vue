<template>
  <div class="flex flex-col">
    <div v-if="!messageStore.hasLoadedThreads" class="flex flex-col gap-3 px-4 py-3" aria-hidden="true">
      <div v-for="i in 3" :key="i" class="flex items-center gap-3">
        <Skeleton shape="circle" size="3rem" />
        <div class="flex flex-1 flex-col gap-2">
          <Skeleton width="50%" height="0.875rem" />
          <Skeleton width="80%" height="0.75rem" />
        </div>
      </div>
    </div>
    <ul v-else-if="latestThreads.length" class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
      <li v-for="threadMeta in latestThreads" :key="threadMeta.thread.id">
        <ConversationRow :thread-meta="threadMeta" compact />
      </li>
    </ul>
    <p v-else class="m-0 px-4 py-6 text-center text-sm text-surface-600 dark:text-surface-400">
      {{ t('message.thread_list.empty') }}
    </p>

    <div class="flex items-center justify-between gap-2 border-t border-surface-200 px-2 pt-1 dark:border-surface-700">
      <RouterLink
        :to="{ name: 'app_messages' }"
        class="inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-primary hover:underline"
      >
        {{ t('message.preview.see_all') }}
      </RouterLink>
      <RouterLink
        :to="{ name: 'app_messages', query: { new: 1 } }"
        class="inline-flex size-11 items-center justify-center rounded-full text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800"
        :aria-label="t('message.index.new_message')"
      >
        <i class="pi pi-pen-to-square" aria-hidden="true" />
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { useMessageStore } from '../../store/message/message.js'
import ConversationRow from './ConversationRow.vue'

/**
 * The envelope's preview: the latest conversations, each one tap from its thread. Looking marks
 * nothing read; only opening a conversation does.
 */
const PREVIEW_SIZE = 5

const { t } = useI18n()
const messageStore = useMessageStore()

// Silent once the inbox has been read: the list on screen stays while it refreshes.
onMounted(() => messageStore.loadThreads({ silent: messageStore.hasLoadedThreads }))

const latestThreads = computed(() => messageStore.orderedThreads.slice(0, PREVIEW_SIZE))
</script>
