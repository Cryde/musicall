<template>
  <div class="flex h-full flex-col">
    <template v-if="messageStore.orderedThreads.length">
      <div class="px-4 pt-3 pb-2">
        <IconField class="w-full">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="query"
            type="search"
            :placeholder="t('message.thread_list.search_placeholder')"
            :aria-label="t('message.thread_list.search_placeholder')"
            class="w-full"
          />
        </IconField>
      </div>

      <ul v-if="visibleThreads.length" class="m-0 flex-1 list-none divide-y divide-surface-200 overflow-y-auto p-0 dark:divide-surface-700">
        <li v-for="threadMeta in visibleThreads" :key="threadMeta.thread.id">
          <ConversationRow
            :thread-meta="threadMeta"
            :is-current="messageStore.currentThreadId === threadMeta.thread.id"
            :now="now"
          />
        </li>
      </ul>
      <p v-else class="m-0 p-4 text-center text-sm text-surface-600 dark:text-surface-400">
        {{ t('message.thread_list.no_match') }}
      </p>
    </template>

    <div v-else class="flex flex-1 items-center justify-center p-4 text-center text-surface-600 dark:text-surface-400">
      {{ t('message.thread_list.empty') }}
    </div>
  </div>
</template>

<script setup>
import { useNow } from '@vueuse/core'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMessageStore } from '../../store/message/message.js'
import { conversationTitle } from '../../utils/conversationIdentity.js'
import { filterConversations } from '../../utils/conversationSearch.js'
import ConversationRow from './ConversationRow.vue'

const { t } = useI18n()
const messageStore = useMessageStore()

const query = ref('')
// A list left open still turns « 09:41 » into « Hier ».
const now = useNow({ interval: 60 * 1000 })

const visibleThreads = computed(() =>
  filterConversations(messageStore.orderedThreads, query.value, (threadMeta) =>
    conversationTitle(threadMeta, messageStore.getOtherParticipant(threadMeta))
  )
)
</script>
