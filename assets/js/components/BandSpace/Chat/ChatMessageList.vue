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

    <div
      v-for="message in chatStore.messages"
      :key="message['@id']"
      class="flex gap-3"
      :class="{ 'flex-row-reverse': isMine(message) }"
    >
      <Avatar
        :username="message.author_username"
        :picture-url="message.author_profile_picture_url"
        size="md"
        class="mt-1 shrink-0"
      />

      <div class="min-w-0 max-w-[75%]" :class="{ 'text-right': isMine(message) }">
        <div
          class="flex items-baseline gap-2 mb-1 text-xs text-surface-500 dark:text-surface-400"
          :class="{ 'flex-row-reverse': isMine(message) }"
        >
          <span class="font-semibold text-surface-700 dark:text-surface-200 truncate">
            {{ message.author_username }}
          </span>
          <span>{{ relativeDate(message.creation_datetime) }}</span>
        </div>

        <div
          class="inline-block rounded-2xl px-4 py-2 text-sm break-words text-left"
          :class="
            isMine(message)
              ? 'bg-primary-700 text-white [&_a]:text-white [&_a]:underline'
              : 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-0 [&_a]:text-primary-500 [&_a]:underline'
          "
          v-html="autoLink(message.content)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { nextTick, onMounted, ref, watch } from 'vue'
import relativeDate from '../../../helper/date/relative-date.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { autoLink } from '../../../utils/autoLink.js'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const chatStore = useBandSpaceChatStore()
const userSecurityStore = useUserSecurityStore()
const messagesContainer = ref(null)

function isMine(message) {
  return message.author_username === userSecurityStore.user?.username
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
