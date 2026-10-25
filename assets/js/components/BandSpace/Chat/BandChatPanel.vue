<template>
  <div class="flex h-full min-h-0 flex-col">
    <div
      v-if="onlineMembers.length > 0"
      class="flex items-center gap-2 px-4 py-2 text-xs text-surface-600 dark:text-surface-300 border-b border-surface-200 dark:border-surface-700"
    >
      <span class="sr-only">{{ t('band_space_chat.view.online_names', { names: onlineMembers.map((member) => member.display_name).join(', ') }) }}</span>
      <span aria-hidden="true">{{ t('band_space_chat.view.online') }}</span>
      <ul class="m-0 p-0 list-none flex items-center gap-1.5" aria-hidden="true">
        <li v-for="member in onlineMembers" :key="member.user_id" v-tooltip.bottom="member.display_name">
          <ChatPresenceAvatar
            :username="member.display_name"
            :picture-url="member.profile_picture_url"
            online
          />
        </li>
      </ul>
    </div>

    <ChatPinnedBar v-if="!chatStore.loadError" :band-space-id="bandSpaceId" />

    <Message
      v-if="chatStore.jumpError"
      severity="warn"
      :closable="true"
      class="m-2"
      @close="chatStore.dismissJumpError()"
    >
      {{ chatStore.jumpError }}
    </Message>

    <div
      v-if="chatStore.loadError"
      class="flex flex-col items-center justify-center flex-1 p-8 gap-4"
    >
      <Message severity="error" :closable="false">{{ chatStore.loadError }}</Message>
      <Button :label="t('band_space_chat.view.retry')" icon="pi pi-refresh" severity="secondary" @click="load" />
    </div>

    <div v-else-if="chatStore.isLoading" class="flex-1 flex items-center justify-center p-8">
      <ProgressSpinner style="width: 2.5rem; height: 2.5rem" />
    </div>

    <div
      v-else-if="chatStore.messages.length === 0"
      class="flex flex-col items-center justify-center flex-1 text-center p-8"
    >
      <i class="pi pi-comments text-4xl text-surface-400 dark:text-surface-500" aria-hidden="true" />
      <p class="mt-3 font-medium">{{ t('band_space_chat.view.empty_title') }}</p>
      <p class="mt-1 text-surface-600 dark:text-surface-300">
        {{ t('band_space_chat.view.empty_text') }}
      </p>
    </div>

    <ChatMessageList
      v-else
      ref="messageList"
      :band-space-id="bandSpaceId"
      :members="settingsStore.members"
    />

    <ChatTypingLine :names="typistNames" />

    <ChatComposer
      :members="settingsStore.members"
      :band-space-id="bandSpaceId"
      media
      :send-message="
        (content, attachments, media) =>
          chatStore.sendMessage(bandSpaceId, content, attachments, media)
      "
      :is-sending="chatStore.isSending"
      @sent="messageList?.scrollToBottom()"
      @typing="chatStore.notifyTyping()"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, onMounted, onUnmounted, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useChatPresence } from '../../../composables/useChatPresence.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'
import { useBandSpaceSettingsStore } from '../../../store/bandSpace/bandSpaceSettings.js'
import { memberNames } from '../../../utils/chatTyping.js'
import ChatComposer from './ChatComposer.vue'
import ChatMessageList from './ChatMessageList.vue'
import ChatPinnedBar from './ChatPinnedBar.vue'
import ChatPresenceAvatar from './ChatPresenceAvatar.vue'
import ChatTypingLine from './ChatTypingLine.vue'

/**
 * A band's chat, everything but the frame around it (#1244): the Band Space tab and « Messages » both
 * show a channel through this, so it reads, writes and updates live the same way in either.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /**
   * The message a link asks to land on (#1039): a task's « Voir la discussion » (#979), a mention
   * notification, a pinned message. It can change while the panel stays mounted, when a notification
   * is opened from the bell with the chat already on screen.
   */
  focusMessageId: { type: String, default: null }
})

const { t } = useI18n()
// Read once: whoever mounts this keys it on the space, so another band means a new panel.
const bandSpaceId = props.bandSpaceId

const chatStore = useBandSpaceChatStore()
// The roster the `@` dropdown filters. Reused from the settings store, which already carries it with a
// staleness token, rather than kept a third time: bandSpaceTasks holds the second copy, and a third
// would be the point to extract a shared one instead.
const settingsStore = useBandSpaceSettingsStore()
const messageList = useTemplateRef('messageList')

// Synchronously, before the first render, so another band's conversation never flashes here.
chatStore.clear()

async function load() {
  // Not awaited: the conversation is what the member came for, and a dropdown that is not usable for
  // another moment costs them nothing.
  settingsStore.loadMembers(bandSpaceId).catch(() => {})
  // Not awaited either: the bar hides itself until it has something, so nothing on screen waits on it.
  chatStore.loadPinnedMessages(bandSpaceId)
  await chatStore.loadMessages(bandSpaceId)
  // Arriving on the tab is reading it, like the direct message inbox does on selecting a thread.
  // After the load rather than before, so a failed load does not claim the member read anything.
  if (!chatStore.loadError) {
    await chatStore.markAsRead(bandSpaceId)
    if (props.focusMessageId) {
      await chatStore.jumpToMessage(bandSpaceId, props.focusMessageId)
    }
  }
}

watch(
  () => props.focusMessageId,
  (messageId) => {
    if (messageId && !chatStore.isLoading) {
      chatStore.jumpToMessage(bandSpaceId, messageId)
    }
  }
)

onMounted(load)

// « En ligne » (#1040). The space never changes under this panel, which is keyed by it.
useChatPresence({
  target: () => bandSpaceId,
  beat: (id) => chatStore.beatPresence(id),
  leave: (id) => chatStore.leavePresence(id)
})

const onlineMembers = computed(() =>
  settingsStore.members.filter((member) => chatStore.onlineUserIds.includes(member.user_id))
)
onUnmounted(() => chatStore.clear())

const typistNames = computed(() => memberNames(chatStore.currentTypists, settingsStore.members))
</script>
