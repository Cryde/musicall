<template>
  <RouterLink
    :to="{ name: BAND_SPACE_ROUTES.CHAT, params: { id: bandSpaceId } }"
    :aria-label="ariaLabel"
    class="flex items-center gap-3 rounded-2xl bg-surface-0 px-3.5 py-3 transition-colors duration-150 hover:bg-surface-50 dark:bg-surface-900 dark:hover:bg-surface-800"
  >
    <i class="pi pi-comments text-xl text-primary" aria-hidden="true" />
    <span class="flex min-w-0 flex-1 flex-col" aria-hidden="true">
      <span class="font-semibold text-surface-900 dark:text-surface-0">{{ t('band_space.module_name.chat') }}</span>
      <span class="truncate text-sm text-surface-600 dark:text-surface-300">{{ preview }}</span>
    </span>
    <Badge v-if="unread > 0" :value="unread > 99 ? '99+' : String(unread)" severity="danger" aria-hidden="true" />
    <span v-else-if="time" class="text-xs text-surface-500 tabular-nums dark:text-surface-400" aria-hidden="true">{{ time }}</span>
    <i class="pi pi-chevron-right text-xs text-surface-500 dark:text-surface-400" aria-hidden="true" />
  </RouterLink>
</template>

<script setup>
import Badge from 'primevue/badge'
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import { displayName } from '../../../helper/user/displayName.js'
import { useMessageStore } from '../../../store/message/message.js'
import { useNotificationStore } from '../../../store/notification/notification.js'
import { chatTimeLabel } from '../../../utils/bandHomeStatus.js'

/**
 * The band chat on the phone home: its latest message and what is unread, as the app's chat row.
 *
 * The preview is the inbox's, which the server words for a message with no text and keeps live. Never
 * the chat store: loading the conversation there marks it read for the member who did not open it.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  // The page's clock, so « 09:07 » turns into « Hier » on a page left open.
  now: { type: Date, required: true }
})

const { t } = useI18n()
const messageStore = useMessageStore()
const notificationStore = useNotificationStore()

onMounted(() => messageStore.loadThreads({ silent: messageStore.hasLoadedThreads }))

const lastMessage = computed(
  () =>
    messageStore.orderedThreads.find((meta) => meta.thread.band_space_id === props.bandSpaceId)
      ?.thread.last_message ?? null
)

const preview = computed(() =>
  lastMessage.value
    ? `${t('message.thread_list.last_message_author', { author: displayName(lastMessage.value.author) })} ${lastMessage.value.content_preview}`
    : t('band_space.home.chat.open')
)

const unread = computed(() => notificationStore.chatUnreadFor(props.bandSpaceId))
const time = computed(() =>
  lastMessage.value ? chatTimeLabel(lastMessage.value.creation_datetime, props.now) : null
)

const ariaLabel = computed(() =>
  [
    t('band_space.module_name.chat'),
    unread.value > 0 ? t('home.member.messages.unread_count', unread.value) : null,
    preview.value
  ]
    .filter(Boolean)
    .join(', ')
)
</script>
