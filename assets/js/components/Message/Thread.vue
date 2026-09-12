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
      <Avatar
        v-if="otherParticipant?.profile_picture?.small && !otherParticipant?.deletion_datetime"
        :image="otherParticipant.profile_picture.small"
        :pt="{ image: { alt: `Photo de ${otherParticipantName}` } }"
        shape="circle"
        role="img"
        :aria-label="`Photo de ${otherParticipantName}`"
      />
      <Avatar
        v-else
        :label="otherParticipantName.charAt(0).toUpperCase()"
        :style="getAvatarStyle(otherParticipantName)"
        shape="circle"
        role="img"
        :aria-label="`Avatar de ${otherParticipantName}`"
      />
      <router-link
        v-if="otherParticipant?.username && !otherParticipant?.deletion_datetime"
        :to="{ name: 'app_user_public_profile', params: { username: otherParticipant.username } }"
        class="font-semibold text-surface-900 dark:text-surface-0 hover:text-primary transition-colors"
      >{{ otherParticipantName }}</router-link>
      <span v-else class="font-semibold text-surface-500">
        {{ otherParticipantName }}
      </span>
    </div>

    <!-- Messages area -->
    <div
      ref="messagesContainer"
      class="flex-1 overflow-y-auto p-4 space-y-4"
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
            label="Charger les messages plus anciens"
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

        <div
          v-for="message in messageStore.messages"
          :key="message['@id']"
          class="flex"
          :class="{ 'justify-end': isSender(message) }"
        >
          <div
            class="max-w-[70%] rounded-lg px-4 py-2"
            :class="isSender(message)
              ? 'bg-primary-700 text-white'
              : 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-0'"
          >
            <div
              class="text-sm break-words"
              :class="{ '[&_a]:text-white [&_a]:underline': isSender(message), '[&_a]:text-primary-500 [&_a]:underline': !isSender(message) }"
              v-html="autoLink(message.content)"
            />
            <div
              class="text-xs mt-1"
              :class="isSender(message) ? 'text-right text-surface-200' : 'opacity-70'"
            >
              {{ relativeDate(message.creation_datetime) }}
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Message input -->
    <div class="border-t border-surface-200 dark:border-surface-700 p-4">
      <p v-if="isRecipientDeleted" class="text-sm text-surface-500 dark:text-surface-400 text-center">
        Vous ne pouvez plus envoyer de message à cet utilisateur.
      </p>
      <template v-else>
        <Message v-if="sendError" severity="error" :closable="true" class="mb-2" @close="sendError = ''">
          {{ sendError }}
        </Message>
        <div class="flex gap-2">
        <Textarea
          ref="messageInput"
          v-model="content"
          :disabled="messageStore.isAddingMessage"
          placeholder="Votre message..."
          rows="2"
          class="flex-1"
          @keydown.enter.exact.prevent="send"
        />
        <Button
          icon="pi pi-send"
          aria-label="Envoyer le message"
          :loading="messageStore.isAddingMessage"
          :disabled="!content.trim() || messageStore.isAddingMessage"
          @click="send"
        />
        </div>
      </template>
    </div>
  </div>

  <div v-else class="hidden md:flex h-full items-center justify-center text-surface-500 dark:text-surface-400 p-4 text-center">
    <div>
      <i class="pi pi-comments text-4xl mb-4 block" />
      <p>Selectionnez une conversation ou envoyez un nouveau message.</p>
    </div>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Textarea from 'primevue/textarea'
import { computed, nextTick, ref, watch } from 'vue'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import { useMessageStore } from '../../store/message/message.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'

const emit = defineEmits(['back'])

const messageStore = useMessageStore()
const securityStore = useUserSecurityStore()

const content = ref('')
const messagesContainer = ref(null)
const messageInput = ref(null)
const sendError = ref('')

const otherParticipant = computed(() => {
  if (!messageStore.currentThread) return null
  return messageStore.getOtherParticipant(messageStore.currentThread)
})

const otherParticipantName = computed(() => {
  return otherParticipant.value ? displayName(otherParticipant.value) : 'Conversation'
})

const isRecipientDeleted = computed(() => !!otherParticipant.value?.deletion_datetime)

function isSender(message) {
  return message.author?.username === securityStore.user?.username
}

function autoLink(str) {
  if (!str) return ''
  // Simple URL linkification
  const urlPattern = /(https?:\/\/[^\s<]+)/g
  return str.replace(urlPattern, '<a href="$1" target="_blank" rel="noopener">$1</a>')
}

async function send() {
  if (!content.value.trim() || messageStore.isAddingMessage) return

  sendError.value = ''
  try {
    await messageStore.postMessageInThread({
      threadId: messageStore.currentThreadId,
      content: content.value
    })
    trackUmamiEvent('message-send')
    content.value = ''
    scrollToBottom()
    nextTick(() => messageInput.value?.$el?.focus())
  } catch (e) {
    if (e.response?.status === 429) {
      sendError.value = 'Trop de messages envoyés. Veuillez patienter un instant.'
    } else {
      console.error('Failed to send message:', e)
    }
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
