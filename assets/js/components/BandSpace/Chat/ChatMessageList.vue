<template>
  <div
    ref="messagesContainer"
    class="flex-1 overflow-y-auto p-4 space-y-4"
    role="log"
    aria-label="Messages de la discussion"
  >
    <div v-if="chatStore.hasOlderMessages" class="flex justify-center">
      <Button
        label="Charger les messages plus anciens"
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
        class="-mb-2 text-center text-xs text-surface-500 dark:text-surface-400"
      >
        <time :datetime="block.messages[0].creation_datetime">
          {{ absoluteDate(block.messages[0].creation_datetime) }}
        </time>
      </p>

      <div
        class="flex gap-3"
        :class="{ 'flex-row-reverse': isMine(block.messages[0]) }"
      >
      <Avatar
        :username="block.messages[0].author_username"
        :picture-url="block.messages[0].author_profile_picture_url"
        size="md"
        class="mt-1 shrink-0"
      />

      <div class="min-w-0 max-w-[75%]" :class="{ 'text-right': isMine(block.messages[0]) }">
        <div
          class="flex items-baseline gap-2 mb-1 text-xs text-surface-500 dark:text-surface-400"
          :class="{ 'flex-row-reverse': isMine(block.messages[0]) }"
        >
          <span class="font-semibold text-surface-700 dark:text-surface-200 truncate">
            {{ block.messages[0].author_username }}
          </span>
        </div>

        <div class="space-y-1">
          <div v-for="(message, index) in block.messages" :key="message['@id']" class="group/message">
            <div
              class="flex items-center gap-2"
              :class="isMine(message) ? 'flex-row-reverse' : 'flex-row'"
            >
              <!-- Editing happens where the bubble is, so the conversation keeps its shape around it
                   and there is no dialog to lose your place in (#966). -->
              <div v-if="editingId === message.id" class="min-w-0 flex-1 text-left">
                <MentionEditor
                  ref="editor"
                  v-model="editContent"
                  :members="suggestionSource"
                  aria-label="Modifier le message"
                  editor-class="min-h-[3rem] max-h-40 rounded-2xl px-3 py-2"
                  submit-on="enter"
                  :disabled="isSaving"
                  @submit="saveEdit(message)"
                  @keydown.esc="cancelEdit"
                />
                <Message v-if="saveError" severity="error" :closable="false" class="mt-1">
                  {{ saveError }}
                </Message>
                <div class="mt-1 flex justify-end gap-2">
                  <Button
                    label="Annuler"
                    size="small"
                    severity="secondary"
                    text
                    :disabled="isSaving"
                    @click="cancelEdit"
                  />
                  <Button
                    label="Enregistrer"
                    size="small"
                    icon="pi pi-check"
                    :loading="isSaving"
                    :disabled="!hasEditChanges || isSaving"
                    @click="saveEdit(message)"
                  />
                </div>
              </div>

              <template v-else>
                <!-- A tombstone keeps its place in the block, muted and never coloured by side: what
                     is left is the fact that somebody wrote here, not the message (#967). -->
                <div
                  v-if="message.is_deleted"
                  class="inline-block rounded-2xl bg-surface-100 px-4 py-2 text-sm italic text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                  :class="bubbleCornerClasses(index, block.messages.length, isMine(message))"
                >
                  Message supprimé
                </div>
                <div
                  v-else
                  class="inline-block rounded-2xl px-4 py-2 text-sm break-words text-left"
                  :class="[
                    isMine(message)
                      ? 'bg-primary-700 text-white [&_a]:text-white [&_a]:underline [&_.chat-mention]:font-semibold [&_.chat-mention]:text-white [&_.chat-mention]:underline [&_.chat-mention]:decoration-white/40'
                      : 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-0 [&_a]:text-primary-500 [&_a]:underline [&_.chat-mention]:font-semibold [&_.chat-mention]:text-primary-700 dark:[&_.chat-mention]:text-primary-300',
                    bubbleCornerClasses(index, block.messages.length, isMine(message)),
                  ]"
                >
                  <div v-html="autoLink(message.content)" />
                  <ChatMessageAttachments
                    v-if="message.attachments?.length"
                    :attachments="message.attachments"
                    :band-space-id="bandSpaceId"
                    :is-mine="isMine(message)"
                  />
                </div>
                <div
                  class="flex shrink-0 items-center gap-1"
                  :class="{ 'flex-row-reverse': isMine(message) }"
                >
                  <!-- Shown without hovering, unlike the time beside it: that an edit happened is part
                       of what the message says, not a detail you go looking for. -->
                  <span
                    v-if="message.update_datetime && !message.is_deleted"
                    class="whitespace-nowrap text-[11px] italic text-surface-500 dark:text-surface-400"
                  >
                    (modifié)
                  </span>
                  <!-- Always rendered so hovering shifts nothing, and readable to a screen reader whether
                       or not there is a pointer to hover with. -->
                  <time
                    :datetime="message.creation_datetime"
                    class="shrink-0 whitespace-nowrap rounded-full bg-surface-200 px-2 py-0.5 text-[11px] text-surface-600 opacity-0 transition-opacity delay-0 duration-150 group-hover/message:opacity-100 group-hover/message:delay-1000 dark:bg-surface-700 dark:text-surface-300"
                  >
                    {{ absoluteDate(message.creation_datetime) }}
                  </time>
                  <!-- Reserves its space like the time above, so the row never moves, but reveals at
                       once rather than after a second: this one is there to be clicked. A pinned
                       message keeps its marker visible, because the bar says what is pinned and this
                       says which one. -->
                  <button
                    v-if="!message.is_deleted"
                    type="button"
                    class="shrink-0 rounded-full bg-surface-200 px-2 py-0.5 text-[11px] text-surface-600 transition-opacity duration-150 hover:text-primary-700 focus-visible:opacity-100 disabled:opacity-50 group-hover/message:opacity-100 dark:bg-surface-700 dark:text-surface-300 dark:hover:text-primary-300"
                    :class="message.is_pinned ? 'opacity-100' : 'opacity-0'"
                    :disabled="chatPin.isPending(message)"
                    :aria-label="chatPin.actionLabel(message)"
                    @click="chatPin.togglePin(message)"
                  >
                    <i
                      class="pi"
                      :class="message.is_pinned ? 'pi-bookmark-fill' : 'pi-bookmark'"
                      aria-hidden="true"
                    />
                  </button>
                  <!-- Hidden until hover only from `lg` up, the treatment the task thread already uses
                       for its own row controls: a touch screen has no hover, so a pencil that only
                       appears on one would be unreachable. Opacity rather than display, so revealing it
                       shifts nothing, and on focus too, or a keyboard lands on a control nothing on
                       screen explains. -->
                  <button
                    v-if="canEdit(message)"
                    type="button"
                    class="shrink-0 rounded-full p-1 text-surface-500 transition-opacity duration-150 hover:text-primary dark:text-surface-400 lg:opacity-0 lg:focus-visible:opacity-100 lg:group-hover/message:opacity-100"
                    aria-label="Modifier le message"
                    @click="startEdit(message)"
                  >
                    <i class="pi pi-pencil text-xs" aria-hidden="true" />
                  </button>
                  <!-- Shown outright below `lg`, where there is no pointer to hover with, and only on
                       hover or focus above it, the same rule TaskCommentList uses. -->
                  <button
                    v-if="canDelete(message)"
                    type="button"
                    class="shrink-0 rounded-full p-1 text-surface-500 transition-opacity duration-150 hover:text-red-600 focus-visible:opacity-100 lg:opacity-0 lg:group-hover/message:opacity-100 dark:text-surface-300 dark:hover:text-red-400"
                    aria-label="Supprimer le message"
                    @click="confirmDelete(message)"
                  >
                    <i class="pi pi-trash text-xs" aria-hidden="true" />
                  </button>
                </div>
              </template>
            </div>

            <ChatMessageReactions
              :band-space-id="bandSpaceId"
              :message-id="message.id"
              :reactions="message.reactions"
              :align-end="isMine(message)"
            />
          </div>
        </div>
      </div>
    </div>
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useChatPin } from '../../../composables/useChatPin.js'
import { EVERYONE_MEMBER } from '../../../constants/chatMention.js'
import absoluteDate from '../../../helper/date/absolute-date.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { autoLink } from '../../../utils/autoLink.js'
import { canDeleteChatMessage } from '../../../utils/chatMessageActions.js'
import { bubbleCornerClasses } from '../../../utils/messageBubbleCorners.js'
import { groupMessages, needsTimeSeparator } from '../../../utils/messageGrouping.js'
import MentionEditor from '../../Global/MentionEditor.vue'
import Avatar from '../../User/Avatar.vue'
import ChatMessageAttachments from './ChatMessageAttachments.vue'
import ChatMessageReactions from './ChatMessageReactions.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** The roster the `@` dropdown of an open edit box filters, supplied by the view that holds it. */
  members: { type: Array, default: () => [] }
})

const chatStore = useBandSpaceChatStore()
const chatPin = useChatPin(props.bandSpaceId)
const userSecurityStore = useUserSecurityStore()
const confirm = useConfirm()
const toast = useToast()
const { isAdmin } = useBandSpaceNavigation()
const messagesContainer = ref(null)

const messageBlocks = computed(() =>
  groupMessages(chatStore.messages, (message) => message.author_id)
)

/** `@tous` is offered here too, or reopening a message that names everybody could not keep it. */
const suggestionSource = computed(() => [EVERYONE_MEMBER, ...props.members])

function isMine(message) {
  return message.author_username === userSecurityStore.user?.username
}

const editor = ref(null)
const editingId = ref(null)
/** The stored `@[uuid]` format, which is what the editor reads and writes and what gets sent. */
const editContent = ref('')
const isSaving = ref(false)
const saveError = ref('')

function canDelete(message) {
  return canDeleteChatMessage(message, isMine(message), isAdmin.value)
}

function confirmDelete(message) {
  confirm.require({
    message: 'Supprimer ce message ? Son contenu sera effacé pour tout le monde.',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: () => handleDelete(message)
  })
}

/**
 * The failure is shown rather than swallowed: the bubble stays exactly as it was, so without a toast
 * a refused delete looks like a click that did nothing.
 */
async function handleDelete(message) {
  try {
    await chatStore.deleteMessage(props.bandSpaceId, message.id)
  } catch (e) {
    console.error('Failed to delete the chat message:', e)
    toast.add({
      severity: 'error',
      summary: e.message || "Le message n'a pas pu être supprimé.",
      life: 5000
    })
  }
}

/**
 * The server sends `editable_content` only on the messages it will accept a PATCH for, so asking it
 * is asking the one authority there is. isMine() above is a different question: it decides which side
 * of the pane a bubble sits on, and it compares usernames.
 */
function canEdit(message) {
  return typeof message.editable_content === 'string'
}

/**
 * Both sides trimmed, not just the typed one: a contenteditable settles on whatever trailing
 * whitespace the browser wants, so comparing it against an untrimmed stored string would light up
 * « Enregistrer » on a message nobody touched and paint « modifié » for nothing.
 */
const hasEditChanges = computed(() => {
  if (editingId.value === null) {
    return false
  }

  const trimmed = editContent.value.trim()
  if (trimmed === '') {
    return false
  }

  const held = chatStore.messages.find((message) => message.id === editingId.value)

  return trimmed !== (held?.editable_content ?? '').trim()
})

function startEdit(message) {
  editingId.value = message.id
  editContent.value = message.editable_content
  saveError.value = ''
  // At the end, because you reopen a message to add to it rather than to overwrite it.
  nextTick(() => editorRef()?.focusAtEnd())
}

/** The editor sits behind a `v-if` in a `v-for`, so Vue hands the ref back as a single element list. */
function editorRef() {
  return Array.isArray(editor.value) ? editor.value[0] : editor.value
}

function cancelEdit() {
  editingId.value = null
  editContent.value = ''
  saveError.value = ''
}

/**
 * The box stays open when the save fails, which is the whole reason this awaits rather than closing
 * on the click: with mentions in it, losing the edit means picking every member again.
 */
async function saveEdit(message) {
  if (!hasEditChanges.value || isSaving.value) {
    return
  }

  isSaving.value = true
  saveError.value = ''

  try {
    await chatStore.editMessage(props.bandSpaceId, message.id, editContent.value.trim())
    cancelEdit()
  } catch (e) {
    console.error('Failed to edit the message:', e)
    saveError.value = e.message || "Le message n'a pas pu être modifié."
  } finally {
    isSaving.value = false
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

    if (grewAtTheEnd && isFollowingTheConversation()) {
      scrollToBottom()
    }
  },
  { deep: true }
)

onMounted(scrollToBottom)

// Sending is the one case where the reader is moved without being asked: they wrote it, so they mean
// to see it, even if they were up in the history a moment ago.
defineExpose({ scrollToBottom })
</script>
