<template>
  <div
    class="bg-surface-0 dark:bg-surface-900 rounded-2xl overflow-hidden flex flex-col h-[calc(100vh-16rem)] min-h-[400px]"
  >
    <p
      v-if="onlineNames.length > 0"
      class="m-0 flex items-center gap-2 px-4 py-2 text-xs text-surface-600 dark:text-surface-300 border-b border-surface-200 dark:border-surface-700"
    >
      <span class="w-2 h-2 rounded-full bg-emerald-500" aria-hidden="true" />
      <span>En ligne : {{ onlineNames.join(', ') }}</span>
    </p>

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
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="load" />
    </div>

    <div v-else-if="chatStore.isLoading" class="flex-1 flex items-center justify-center p-8">
      <ProgressSpinner style="width: 2.5rem; height: 2.5rem" />
    </div>

    <div
      v-else-if="chatStore.messages.length === 0"
      class="flex flex-col items-center justify-center flex-1 text-center p-8"
    >
      <i class="pi pi-comments text-4xl text-surface-400 dark:text-surface-500" aria-hidden="true" />
      <p class="mt-3 font-medium">Aucun message</p>
      <p class="mt-1 text-surface-600 dark:text-surface-300">
        Écrivez le premier message du groupe, il restera ici pour tout le monde.
      </p>
    </div>

    <ChatMessageList
      v-else
      ref="messageList"
      :band-space-id="bandSpaceId"
      :members="settingsStore.members"
    />

    <!-- Always in the page, so a screen reader hears it change; polite, so it never cuts in. -->
    <p class="min-h-5 m-0 px-4 text-xs italic text-surface-600 dark:text-surface-300" aria-live="polite">
      {{ typingText }}
    </p>

    <ChatComposer
      :members="settingsStore.members"
      :band-space-id="bandSpaceId"
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
import { useRoute } from 'vue-router'
import ChatComposer from '../../components/BandSpace/Chat/ChatComposer.vue'
import ChatMessageList from '../../components/BandSpace/Chat/ChatMessageList.vue'
import ChatPinnedBar from '../../components/BandSpace/Chat/ChatPinnedBar.vue'
import { useBandSpaceChatStore } from '../../store/bandSpace/bandSpaceChat.js'
import { useBandSpaceSettingsStore } from '../../store/bandSpace/bandSpaceSettings.js'
import { memberNames, typingSentence } from '../../utils/chatTyping.js'
import { createHeartbeat } from '../../utils/heartbeat.js'
import { createPresenceSession } from '../../utils/presenceSession.js'

const route = useRoute()
// Read once: AppBandLayout keys <router-view> on the space id, so this view is remounted rather than
// reused when the member switches band.
const bandSpaceId = route.params.id

/**
 * The message a link asks to land on (#1039): a task's « Voir la discussion » (#979), a mention
 * notification, a pinned message. Unlike the space id it can change while the view stays mounted,
 * when a notification is opened from the bell with the tab already on screen.
 */
function requestedMessageId() {
  return typeof route.query.message === 'string' ? route.query.message : null
}

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
    const messageId = requestedMessageId()
    if (messageId) {
      await chatStore.jumpToMessage(bandSpaceId, messageId)
    }
  }
}

watch(
  () => route.query.message,
  (messageId) => {
    if (typeof messageId === 'string' && !chatStore.isLoading) {
      chatStore.jumpToMessage(bandSpaceId, messageId)
    }
  }
)

onMounted(load)

// « En ligne » (#1040): only while this page is open and on screen. A tab hidden for longer than the
// grace says it has gone, and beats again, at once, when it is shown.
const PRESENCE_INTERVAL_MS = 30000
const PRESENCE_HIDDEN_GRACE_MS = 15000
const presence = createPresenceSession({
  heartbeat: createHeartbeat({
    intervalMs: PRESENCE_INTERVAL_MS,
    beat: () => chatStore.beatPresence(bandSpaceId)
  }),
  leave: () => chatStore.leavePresence(bandSpaceId),
  graceMs: PRESENCE_HIDDEN_GRACE_MS
})

function syncPresenceWithVisibility() {
  if (document.visibilityState === 'visible') presence.shown()
  else presence.hidden()
}

// A page restored from the browser's back-forward cache fires no visibilitychange, only pageshow.
function resumeOnPageShow(event) {
  if (event.persisted) syncPresenceWithVisibility()
}

onMounted(() => {
  syncPresenceWithVisibility()
  document.addEventListener('visibilitychange', syncPresenceWithVisibility)
  window.addEventListener('pagehide', presence.leaveNow)
  window.addEventListener('pageshow', resumeOnPageShow)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', syncPresenceWithVisibility)
  window.removeEventListener('pagehide', presence.leaveNow)
  window.removeEventListener('pageshow', resumeOnPageShow)
  presence.leaveNow()
})

const onlineNames = computed(() => memberNames(chatStore.onlineUserIds, settingsStore.members))
onUnmounted(() => chatStore.clear())

const typingText = computed(() =>
  typingSentence(memberNames(chatStore.currentTypists, settingsStore.members))
)
</script>
