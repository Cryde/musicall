<template>
  <div v-if="messageStore.currentThreadId" class="h-full flex flex-col">
    <!-- Header with back button on mobile -->
    <div class="flex items-center gap-3 p-4 border-b border-surface-200 dark:border-surface-700">
      <div class="md:hidden">
        <Button
          icon="pi pi-arrow-left"
          text
          rounded
          @click="emit('back')"
        />
      </div>
      <!-- The dot is the band chat's (#1040): the other person has this conversation open (#1246). -->
      <span class="relative inline-flex shrink-0">
        <Avatar
          v-if="isCurrentChannel"
          icon="pi pi-users"
          :style="getAvatarStyle(title)"
          shape="circle"
          role="img"
          :aria-label="t('message.avatar.channel', { name: title })"
        />
        <Avatar
          v-else-if="otherParticipant?.profile_picture?.small && !otherParticipant?.deletion_datetime"
          :image="otherParticipant.profile_picture.small"
          :pt="{ image: { alt: t('message.avatar.photo', { name: title }) } }"
          shape="circle"
          role="img"
          :aria-label="t('message.avatar.photo', { name: title })"
        />
        <Avatar
          v-else
          :label="title.charAt(0).toUpperCase()"
          :style="getAvatarStyle(otherParticipant?.username ?? title)"
          shape="circle"
          role="img"
          :aria-label="t('message.avatar.initial', { name: title })"
        />
        <span
          v-if="isOtherOnline"
          class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-surface-0 dark:ring-surface-900"
          aria-hidden="true"
        />
      </span>
      <!-- A channel points at the band's own discussion tab, the way a direct message points at the
           other party's profile: the header's name is a way back to where the conversation lives. -->
      <router-link
        v-if="isCurrentChannel"
        :to="{ name: BAND_SPACE_ROUTES.CHAT, params: { id: currentThread.thread.band_space_id } }"
        class="font-semibold text-surface-900 dark:text-surface-0 hover:text-primary transition-colors truncate"
      >{{ title }}</router-link>
      <UserName
        v-else-if="otherParticipant?.username && !otherParticipant?.deletion_datetime"
        :username="otherParticipant.username"
        :display-name="title"
        :to="{ name: 'app_user_public_profile', params: { username: otherParticipant.username } }"
        class="font-semibold text-surface-900 dark:text-surface-0 hover:text-primary transition-colors"
      />
      <span v-else class="font-semibold text-surface-500">
        {{ title }}
      </span>
      <span v-if="isCurrentChannel" class="text-sm text-surface-600 dark:text-surface-400 shrink-0">
        #{{ currentThread.thread.channel_name }}
      </span>
      <span v-if="isOtherOnline" class="text-xs text-emerald-700 dark:text-emerald-400 shrink-0">
        {{ t('message.thread.online') }}
      </span>
      <BlockUserButton
        v-if="!isCurrentChannel && otherParticipant?.id && !isRecipientDeleted"
        :user-id="otherParticipant.id"
        :username="otherParticipant.username"
        icon-only
        class="ml-auto"
        @blocked="handleBlocked"
      />
    </div>

    <!-- A channel is the band chat itself (#1244): reactions, media, « Vu par », typing, all of it,
         read and written exactly as on the Band Space tab. -->
    <BandChatPanel
      v-if="isCurrentChannel"
      :key="currentThread.thread.band_space_id"
      :band-space-id="currentThread.thread.band_space_id"
      class="flex-1"
    />

    <template v-else>
      <!-- Messages area -->
      <div
        ref="messagesContainer"
        class="@container flex-1 overflow-y-auto p-4 space-y-4"
      >
        <ProgressSpinner
          v-if="messageStore.isLoadingMessages"
          class="flex justify-center"
        />

        <template v-else>
          <div
            v-if="messageStore.hasOlderMessages"
            class="flex justify-center"
          >
            <Button
              :label="t('message.thread.load_older')"
              severity="secondary"
              outlined
              size="small"
              :loading="messageStore.isLoadingOlderMessages"
              @click="handleLoadOlder"
            />
          </div>
          <Message
            v-if="messageStore.loadOlderError"
            severity="error"
            :closable="false"
          >
            {{ messageStore.loadOlderError }}
          </Message>
          <Message v-if="messageStore.reactionError" severity="error" :closable="false">
            {{ messageStore.reactionError }}
          </Message>

          <!-- One block per burst (#1031): consecutive messages from one side share their corners. -->
          <template v-for="(block, blockIndex) in messageBlocks" :key="block.messages[0]['@id']">
          <!-- Dated only when the conversation resumes after a silence, so a busy exchange is marked
               once at its start rather than every time the speaker changes. -->
          <p
            v-if="needsTimeSeparator(messageBlocks[blockIndex - 1], block)"
            class="mb-2 text-center text-xs text-surface-500 dark:text-surface-400"
          >
            <time :datetime="block.messages[0].creation_datetime">
              {{ absoluteDate(block.messages[0].creation_datetime) }}
            </time>
          </p>

          <div
            class="flex flex-col gap-1"
            :class="{ 'items-end': isSender(block.messages[0]) }"
          >
            <div
              v-for="(message, index) in block.messages"
              :key="message['@id']"
              class="group/message flex max-w-[85%] flex-col"
            >
              <ContactOrigin
                v-if="message.contact_origin"
                :origin="message.contact_origin"
                class="mt-2 mb-1"
                :class="isSender(message) ? 'self-end' : 'self-start'"
              />
              <!-- The band chat's bubble (#1244), with its edit, delete and reactions (#1245). No admin
                   here, so only the author deletes. -->
              <ChatMessageBubble
                :message="message"
                :is-mine="isSender(message)"
                :corner-classes="bubbleCornerClasses(index, block.messages.length, isSender(message))"
                :is-editing="editingId === message.id"
                :image-src="message.image ? messageApi.imageUrl(messageStore.currentThreadId, message.image.file_id) : null"
                :load-voice-note="loadVoiceNote"
                reactable
                can-report
                :can-edit="canEdit(message)"
                :can-delete="canDeleteChatMessage(message, isSender(message), false)"
                @report="openReport(message)"
                @edit="startEdit(message)"
                @delete="confirmDelete(message)"
                @react="(emojiKey) => messageStore.toggleReaction(message.id, emojiKey)"
              >
                <!-- Nobody to mention in a conversation of two, so `@` stays plain text. -->
                <ChatMessageEditBox
                  v-model="editContent"
                  :is-saving="isSaving"
                  :error="saveError"
                  :can-save="hasEditChanges"
                  @save="saveEdit"
                  @cancel="cancelEdit"
                />
              </ChatMessageBubble>
            </div>
          </div>
          </template>

          <!-- Under your newest message only, once the other person has seen it (#1243). Once they reply,
               their answer says it better. -->
          <p v-if="isNewestSeen" class="-mt-3 text-right text-xs text-surface-500 dark:text-surface-400">
            {{ t('message.thread.seen') }}
          </p>
        </template>
      </div>

      <ChatTypingLine :names="typistNames" />

      <p
        v-if="isRecipientDeleted"
        class="m-0 border-t border-surface-200 p-4 text-center text-sm text-surface-500 dark:border-surface-700 dark:text-surface-400"
      >
        {{ t('message.thread.recipient_deleted') }}
      </p>
      <!-- The band chat's composer with nobody to mention: Enter sends, and the server's own sentence
           shows when it refuses, a block from either side included. -->
      <ChatComposer
        v-else
        :mentions="false"
        :media="canSendMedia"
        :send-message="sendToThread"
        :is-sending="messageStore.isAddingMessage"
        @sent="scrollToBottom"
        @typing="messageStore.notifyTyping()"
      />

      <ReportDialog v-model:visible="isReportVisible" target-type="message" :target-id="reportedMessageId" />
    </template>
  </div>

  <div v-else class="hidden md:flex h-full items-center justify-center text-surface-500 dark:text-surface-400 p-4 text-center">
    <div>
      <i class="pi pi-comments text-4xl mb-4 block" />
      <p>{{ t('message.thread.none_selected') }}</p>
    </div>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import messageApi from '../../api/message/message.js'
import { handleApiError } from '../../api/utils/handleApiError.js'
import BandChatPanel from '../../components/BandSpace/Chat/BandChatPanel.vue'
import ChatComposer from '../../components/BandSpace/Chat/ChatComposer.vue'
import ChatMessageBubble from '../../components/BandSpace/Chat/ChatMessageBubble.vue'
import ChatMessageEditBox from '../../components/BandSpace/Chat/ChatMessageEditBox.vue'
import ChatTypingLine from '../../components/BandSpace/Chat/ChatTypingLine.vue'
import { useChatMessageActions } from '../../composables/useChatMessageActions.js'
import { useChatPresence } from '../../composables/useChatPresence.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import absoluteDate from '../../helper/date/absolute-date.js'
import { useMessageStore } from '../../store/message/message.js'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { canDeleteChatMessage } from '../../utils/chatMessageActions.js'
import { conversationTitle, isChannel } from '../../utils/conversationIdentity.js'
import { bubbleCornerClasses } from '../../utils/messageBubbleCorners.js'
import { groupMessages, needsTimeSeparator } from '../../utils/messageGrouping.js'
import ReportDialog from '../Report/ReportDialog.vue'
import BlockUserButton from '../User/Block/BlockUserButton.vue'
import UserName from '../User/UserName.vue'
import ContactOrigin from './ContactOrigin.vue'

const emit = defineEmits(['back'])

const { t } = useI18n()
const messageStore = useMessageStore()
const securityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()
const messagesContainer = ref(null)
const isReportVisible = ref(false)
const reportedMessageId = ref(null)

const currentThread = computed(() => messageStore.currentThread)
const isCurrentChannel = computed(() => isChannel(currentThread.value))

const messageBlocks = computed(() =>
  groupMessages(messageStore.messages, (message) => message.author?.id)
)

const otherParticipant = computed(() => {
  if (!currentThread.value) return null
  return messageStore.getOtherParticipant(currentThread.value)
})

// Shared with the list rather than computed here, which is how the two stopped disagreeing on a
// thread with no participant: this read it as « Conversation » and the list as « Utilisateur
// inconnu » (#994).
const title = computed(() => conversationTitle(currentThread.value, otherParticipant.value))

const isRecipientDeleted = computed(() => !!otherParticipant.value?.deletion_datetime)

// « En ligne » (#1246) for a direct conversation only: a channel is BandChatPanel's, which beats for
// its band, and a deleted account has nobody to be present with. Not until the inbox has loaded the
// thread, or a channel opened from a link would be beaten here as if it were direct.
useChatPresence({
  target: () =>
    currentThread.value && !isCurrentChannel.value && !isRecipientDeleted.value
      ? messageStore.currentThreadId
      : null,
  beat: (threadId) => messageStore.beatPresence(threadId),
  leave: (threadId) => messageStore.leavePresence(threadId)
})

const isOtherOnline = computed(
  () =>
    !isCurrentChannel.value &&
    !!otherParticipant.value?.id &&
    messageStore.onlineUserIds.includes(otherParticipant.value.id)
)

// Images and voice notes once the other person has written here (#1247). The inbox says so, and a reply
// arriving while the conversation is open says so before the inbox has reloaded.
const canSendMedia = computed(
  () =>
    currentThread.value?.thread?.can_send_media === true ||
    messageStore.messages.some((message) => !isSender(message))
)

// Two people in a conversation, so whoever is writing is the one the header names.
const typistNames = computed(() => (messageStore.currentTypists.length > 0 ? [title.value] : []))

const isNewestSeen = computed(() => {
  const newest = messageStore.messages.at(-1)
  return !!newest && isSender(newest) && newest.is_seen === true
})

// The conversation leaves the inbox and its badge once blocked (#1117), so both are reloaded and the
// pane closes with it. « Débloquer » stays on the profile and in the privacy settings.
function handleBlocked() {
  messageStore.loadThreads({ silent: true }).catch(() => {})
  notificationStore.loadNotifications().catch(() => {})
  emit('back')
}

function isSender(message) {
  return message.author?.username === securityStore.user?.username
}

function openReport(message) {
  reportedMessageId.value = message.id
  isReportVisible.value = true
}

const {
  editingId,
  editContent,
  isSaving,
  saveError,
  hasEditChanges,
  canEdit,
  startEdit,
  cancelEdit,
  saveEdit,
  confirmDelete
} = useChatMessageActions({
  messages: () => messageStore.messages,
  edit: (messageId, content) => messageStore.editMessage(messageId, content),
  remove: (messageId) => messageStore.deleteMessage(messageId)
})

// An edit box left open would follow the reader into the next conversation.
watch(
  () => messageStore.currentThreadId,
  () => cancelEdit()
)

// Read at play time rather than bound to a thread, since the list is not remounted on a switch.
function loadVoiceNote(fileId) {
  return messageApi.getVoiceNote(messageStore.currentThreadId, fileId)
}

/**
 * Normalised so the composer shows the server's own sentence, or its rate limit wording on a 429.
 * A conversation carries no band attachments, only an image or a voice note (#1247).
 */
async function sendToThread(content, _attachments, media) {
  try {
    await messageStore.postMessageInThread({
      threadId: messageStore.currentThreadId,
      content,
      media
    })
  } catch (e) {
    handleApiError(e)
  }
  trackUmamiEvent('message-send')
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

/** Within a screenful of the end, which is close enough to count as following the conversation. */
const FOLLOWING_THRESHOLD_PX = 120

function isFollowingTheConversation() {
  const container = messagesContainer.value
  if (!container) {
    return true
  }

  return (
    container.scrollHeight - container.scrollTop - container.clientHeight < FOLLOWING_THRESHOLD_PX
  )
}

/**
 * Loading history keeps the reader where they were.
 *
 * Prepending grows the list upwards, so without compensating, the message being read slides down by
 * however tall the new page is. Measured either side of the DOM update and the difference added back
 * to the offset, which is the whole trick.
 */
async function handleLoadOlder() {
  const container = messagesContainer.value
  const previousScrollHeight = container?.scrollHeight ?? 0
  const previousScrollTop = container?.scrollTop ?? 0

  await messageStore.loadOlderMessages()
  await nextTick()

  if (container) {
    container.scrollTop = previousScrollTop + (container.scrollHeight - previousScrollHeight)
  }
}

// Scroll to bottom when messages change, unless the reader has gone back up. Messages now arrive on
// their own (#989), so yanking the view to the bottom would interrupt somebody rereading history
// rather than follow along with them. Read before the DOM updates, which is what a pre-flush watcher
// gives us, so this is where the reader was rather than where the new content puts them.
//
// Only when the list grew at its *end*. A conversation shorter than its pane has nothing to scroll,
// so isFollowingTheConversation() is trivially true there, and loading history into it would jump to
// the bottom having just been asked for the top. The last message's identity says which end moved.
//
// Reset per thread, because this component is not remounted when you switch conversation: the
// messages route renders through AppBaseLayout's router-view, which carries no `:key`, unlike the
// band space layout. Without this, arriving back at a thread whose last message has not changed since
// you left would read as "nothing grew" and suppress the scroll to the bottom.
let lastTailIri = null
watch(
  () => messageStore.currentThreadId,
  () => {
    lastTailIri = null
  }
)
watch(
  () => messageStore.messages,
  (currentMessages) => {
    const tailIri = currentMessages.at(-1)?.['@id'] ?? null
    const grewAtTheEnd = tailIri !== lastTailIri
    lastTailIri = tailIri

    if (grewAtTheEnd && isFollowingTheConversation()) {
      scrollToBottom()
    }
  },
  { deep: true }
)

// Scroll to bottom when loading finishes
watch(
  () => messageStore.isLoadingMessages,
  (isLoading) => {
    if (!isLoading) {
      scrollToBottom()
    }
  }
)
</script>
