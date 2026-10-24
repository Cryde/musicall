<template>
  <div
    ref="messagesContainer"
    class="@container flex-1 overflow-y-auto p-4 space-y-4"
    role="log"
    :aria-label="t('band_space_chat.message_list.label')"
  >
    <div v-if="chatStore.hasOlderMessages" class="flex justify-center">
      <Button
        :label="t('band_space_chat.message_list.load_older')"
        severity="secondary"
        outlined
        size="small"
        :loading="chatStore.isLoadingOlder"
        @click="handleLoadOlder"
      />
    </div>

    <Message v-if="chatStore.loadOlderError" severity="error" :closable="false">
      {{ chatStore.loadOlderError }}
    </Message>

    <Message v-if="chatStore.reactionError" severity="error" :closable="false">
      {{ chatStore.reactionError }}
    </Message>

    <!-- One block per burst (#1031): the avatar, the name and the time belong to the block, and only
         the bubbles repeat inside it. -->
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
        class="flex gap-3"
        :class="{ 'flex-row-reverse': isMine(block.messages[0]) }"
      >
      <ChatPresenceAvatar
        :username="block.messages[0].author_username"
        :display-name="block.messages[0].author_display_name"
        :picture-url="block.messages[0].author_profile_picture_url"
        :online="isOnline(block.messages[0])"
        class="mt-1"
      />

      <div class="min-w-0 max-w-[75%]" :class="{ 'text-right': isMine(block.messages[0]) }">
        <div
          class="flex items-baseline gap-2 mb-1 text-xs text-surface-500 dark:text-surface-400"
          :class="{ 'flex-row-reverse': isMine(block.messages[0]) }"
        >
          <span class="font-semibold text-surface-700 dark:text-surface-200 truncate">
            <UserName
              :username="block.messages[0].author_username"
              :display-name="block.messages[0].author_display_name"
              variant="tooltip"
            />
            <span v-if="isOnline(block.messages[0])" class="sr-only">{{ t('band_space_chat.message_list.online') }}</span>
          </span>
        </div>

        <div class="space-y-1">
          <div
            v-for="(message, index) in block.messages"
            :id="`chat-message-${message.id}`"
            :key="message['@id']"
            class="group/message rounded-2xl transition-shadow duration-300"
            :class="{ 'ring-2 ring-primary-500': highlightedMessageId === message.id }"
          >
            <ChatMessageBubble
              :message="message"
              :is-mine="isMine(message)"
              :corner-classes="bubbleCornerClasses(index, block.messages.length, isMine(message))"
              :is-editing="editingId === message.id"
              :image-src="message.image ? bandSpaceChatApi.imageUrl(bandSpaceId, message.image.file_id) : null"
              :load-voice-note="loadVoiceNote"
              reactable
              can-pin
              :is-pin-pending="chatPin.isPending(message)"
              :pin-label="chatPin.actionLabel(message)"
              can-create-task
              :is-task-pending="chatStore.pendingTaskMessageId === message.id"
              can-report
              :can-edit="canEdit(message)"
              :can-delete="canDelete(message)"
              @pin="chatPin.togglePin(message)"
              @create-task="handleCreateTask(message)"
              @report="openReport(message)"
              @edit="startEdit(message)"
              @delete="confirmDelete(message)"
              @react="(emojiKey) => chatStore.toggleReaction(bandSpaceId, message.id, emojiKey)"
            >
              <ChatMessageEditBox
                v-model="editContent"
                :members="suggestionSource"
                :is-saving="isSaving"
                :error="saveError"
                :can-save="hasEditChanges"
                @save="saveEdit"
                @cancel="cancelEdit"
              />
              <template #attachments>
                <ChatMessageAttachments
                  v-if="message.attachments?.length"
                  :attachments="message.attachments"
                  :band-space-id="bandSpaceId"
                  :is-mine="isMine(message)"
                />
              </template>
            </ChatMessageBubble>
          </div>
        </div>
      </div>
    </div>
    </template>

    <!-- Under the last message only, whoever wrote it: « Vu par » repeated under fifty bubbles is
         noise, and the question a band asks is always about the newest one (#977). -->
    <p
      v-if="readReceipt && chatStore.isAtTail"
      class="text-xs text-surface-500 dark:text-surface-400"
      :class="{ 'text-right': lastMessageIsMine }"
    >
      {{ readReceipt }}
    </p>

    <!-- Reading a window up the history (#1039): the conversation goes on below it, and the way back
         to the present stays in reach however far the member has scrolled. -->
    <template v-if="chatStore.hasNewerMessages">
      <Message v-if="chatStore.loadNewerError" severity="error" :closable="false">
        {{ chatStore.loadNewerError }}
      </Message>
      <div class="flex justify-center">
        <Button
          :label="t('band_space_chat.message_list.load_newer')"
          severity="secondary"
          outlined
          size="small"
          :loading="chatStore.isLoadingNewer"
          @click="handleLoadNewer"
        />
      </div>
      <div class="sticky bottom-0 flex justify-center pb-1">
        <Button
          :label="t('band_space_chat.message_list.back_to_latest')"
          icon="pi pi-arrow-down"
          size="small"
          rounded
          class="shadow-md"
          @click="chatStore.returnToLatest(bandSpaceId)"
        />
      </div>
    </template>

    <ReportDialog v-model:visible="isReportVisible" target-type="band_chat_message" :target-id="reportedMessageId" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceChatApi from '../../../api/bandSpace/band-space-chat.js'
import { useChatMessageActions } from '../../../composables/useChatMessageActions.js'
import { useChatPin } from '../../../composables/useChatPin.js'
import { EVERYONE_MEMBER } from '../../../constants/chatMention.js'
import absoluteDate from '../../../helper/date/absolute-date.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { canDeleteChatMessage } from '../../../utils/chatMessageActions.js'
import { readReceiptLabel } from '../../../utils/chatReadReceipt.js'
import { bubbleCornerClasses } from '../../../utils/messageBubbleCorners.js'
import { groupMessages, needsTimeSeparator } from '../../../utils/messageGrouping.js'
import ReportDialog from '../../Report/ReportDialog.vue'
import UserName from '../../User/UserName.vue'
import ChatMessageAttachments from './ChatMessageAttachments.vue'
import ChatMessageBubble from './ChatMessageBubble.vue'
import ChatMessageEditBox from './ChatMessageEditBox.vue'
import ChatPresenceAvatar from './ChatPresenceAvatar.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /**
   * The active roster, supplied by the view that holds it: the `@` dropdown of an open edit box filters
   * it, and the viewer's own row says whether they may delete other people's messages.
   */
  members: { type: Array, default: () => [] }
})

const { t } = useI18n()
const chatStore = useBandSpaceChatStore()
const chatPin = useChatPin(props.bandSpaceId)
const userSecurityStore = useUserSecurityStore()
const toast = useToast()
// From the roster rather than the route: « Messages » shows this list with no space in its URL, and
// the space list holding the role is only loaded by the band layout (#1244).
const isAdmin = computed(() =>
  props.members.some(
    (member) => member.user_id === userSecurityStore.userProfile?.id && member.role === 'admin'
  )
)
const messagesContainer = ref(null)
const isReportVisible = ref(false)
const reportedMessageId = ref(null)

const messageBlocks = computed(() =>
  groupMessages(chatStore.messages, (message) => message.author_id)
)

/** `@tous` is offered here too, or reopening a message that names everybody could not keep it. */
const suggestionSource = computed(() => [EVERYONE_MEMBER, ...props.members])

function isMine(message) {
  return message.author_username === userSecurityStore.user?.username
}

// The presence answer lists the others only, so my own avatar is never marked.
function isOnline(message) {
  return chatStore.onlineUserIds.includes(message.author_id)
}

// Held oldest first, so the last message of the list is the newest one of the conversation.
const lastMessage = computed(() => chatStore.messages.at(-1) ?? null)
const readReceipt = computed(() => readReceiptLabel(lastMessage.value?.read_by_display_names))
const lastMessageIsMine = computed(() => lastMessage.value !== null && isMine(lastMessage.value))

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
  messages: () => chatStore.messages,
  edit: (messageId, content) => chatStore.editMessage(props.bandSpaceId, messageId, content),
  remove: (messageId) => chatStore.deleteMessage(props.bandSpaceId, messageId)
})

function loadVoiceNote(fileId) {
  return bandSpaceChatApi.getVoiceNote(props.bandSpaceId, fileId)
}

function openReport(message) {
  reportedMessageId.value = message.id
  isReportVisible.value = true
}

function canDelete(message) {
  return canDeleteChatMessage(message, isMine(message), isAdmin.value)
}

/**
 * The message keeps its card whatever happens next: the store adds it from the answer, so the bubble
 * points at the task straight away and the card is the way to it (#979).
 */
async function handleCreateTask(message) {
  try {
    const task = await chatStore.createTaskFromMessage(props.bandSpaceId, message.id)
    toast.add({
      severity: 'success',
      summary: t('band_space_chat.message_list.task_created'),
      // Names the card, because the card is the way back: it opens the task, where the assignee and
      // the date are set. A toast that only congratulates leaves the member hunting the board.
      detail: t('band_space_chat.message_list.task_created_detail', { title: task.title }),
      life: 6000
    })
  } catch (e) {
    console.error('Failed to create a task from the chat message:', e)
    toast.add({
      severity: 'error',
      summary: e.message || t('band_space_chat.message_list.task_failed'),
      life: 5000
    })
  }
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
 * Keeps the reader where they were while the page grows above them: the message under the cursor has
 * to stay under the cursor, so the scroll position moves by exactly how much taller the list got.
 */
async function handleLoadOlder() {
  const container = messagesContainer.value
  const previousScrollHeight = container?.scrollHeight ?? 0
  const previousScrollTop = container?.scrollTop ?? 0

  await chatStore.loadOlderMessages(props.bandSpaceId)
  await nextTick()

  if (container) {
    container.scrollTop = previousScrollTop + (container.scrollHeight - previousScrollHeight)
  }
}

/**
 * Reading on towards the present from a window: the new rows land below the reader, who keeps their
 * place, so the follow rule below must not take them to the bottom of what was just loaded.
 */
let isLoadingNewer = false

async function handleLoadNewer() {
  isLoadingNewer = true
  try {
    await chatStore.loadNewerMessages(props.bandSpaceId)
    await nextTick()
  } finally {
    isLoadingNewer = false
  }
}

/**
 * Deliberately not `flush: 'post'`: the scroll position is read before the DOM updates, so it tells
 * us where the reader was rather than where the browser has already put them.
 *
 * Comparing the last `@id` is what separates a new message at the end from a page prepended at the
 * top. Height alone cannot: a conversation shorter than its pane is always "at the bottom", so a
 * prepend would scroll the reader away from the history they just asked for.
 */
let lastTailIri = null

watch(
  () => chatStore.messages,
  (currentMessages) => {
    const tailIri = currentMessages.at(-1)?.['@id'] ?? null
    const grewAtTheEnd = tailIri !== lastTailIri
    lastTailIri = tailIri

    // Never while a window is shown or read on: following only means something at the live end.
    if (grewAtTheEnd && chatStore.isAtTail && !isLoadingNewer && isFollowingTheConversation()) {
      scrollToBottom()
    }
  },
  { deep: true }
)

/** Long enough to find the message with your eyes, short enough not to become part of the page. */
const HIGHLIGHT_DURATION_MS = 2500

const highlightedMessageId = ref(null)
let highlightTimer = null

/**
 * Scrolls to the message the store was asked to show and lights it up (#1039). By the time a request
 * arrives the message is held, whether it was already on the page or a window was loaded around it,
 * so there is always something to land on. A frame after the DOM update rather than on it, so the
 * landing wins over anything else moving the pane in the same tick.
 */
function focusMessage(messageId) {
  nextTick(() => {
    requestAnimationFrame(() => {
      const element = document.getElementById(`chat-message-${messageId}`)
      if (!element) {
        return
      }

      element.scrollIntoView({ block: 'center' })
      highlightedMessageId.value = messageId
      clearTimeout(highlightTimer)
      highlightTimer = setTimeout(() => {
        highlightedMessageId.value = null
      }, HIGHLIGHT_DURATION_MS)
    })
  })
}

watch(
  () => chatStore.focusRequest,
  (request) => {
    if (request) {
      focusMessage(request.messageId)
    }
  }
)

onMounted(() => {
  // A jump that replaced an empty pane mounts this list with its request already made.
  if (chatStore.focusRequest) {
    focusMessage(chatStore.focusRequest.messageId)

    return
  }
  scrollToBottom()
})

onUnmounted(() => clearTimeout(highlightTimer))

// Sending is the one case where the reader is moved without being asked: they wrote it, so they mean
// to see it, even if they were up in the history a moment ago.
defineExpose({ scrollToBottom })
</script>
