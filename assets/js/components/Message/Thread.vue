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
        v-if="otherParticipant?.profile_picture?.small"
        :image="otherParticipant.profile_picture.small"
        shape="circle"
      />
      <Avatar
        v-else
        :label="otherParticipant?.username?.charAt(0).toUpperCase() || '?'"
        shape="circle"
      />
      <span class="font-semibold text-surface-900 dark:text-surface-0">
        {{ otherParticipant?.username || 'Conversation' }}
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
          v-for="message in messageStore.messages"
          :key="message.id"
          class="flex"
          :class="{ 'justify-end': isSender(message) }"
        >
          <div
            class="max-w-[70%] rounded-lg px-4 py-2"
            :class="isSender(message)
              ? 'bg-primary-500 text-white'
              : 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-0'"
          >
            <div
              class="text-sm break-words"
              :class="{ '[&_a]:text-white [&_a]:underline': isSender(message), '[&_a]:text-primary-500 [&_a]:underline': !isSender(message) }"
              v-html="autoLink(message.content)"
            />
            <div
              class="text-xs mt-1 opacity-70"
              :class="isSender(message) ? 'text-right' : ''"
            >
              {{ relativeDate(message.creation_datetime) }}
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Message input -->
    <div class="border-t border-surface-200 dark:border-surface-700 p-4">
      <div class="flex gap-2">
        <Textarea
          v-model="content"
          :disabled="messageStore.isAddingMessage"
          placeholder="Votre message..."
          rows="2"
          class="flex-1"
          @keydown.enter.exact.prevent="send"
        />
        <Button
          icon="pi pi-send"
          :loading="messageStore.isAddingMessage"
          :disabled="!content.trim() || messageStore.isAddingMessage"
          @click="send"
        />
      </div>
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
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import Textarea from 'primevue/textarea'
import { computed, nextTick, ref, watch } from 'vue'
import relativeDate from '../../helper/date/relative-date.js'
import { useMessageStore } from '../../store/message/message.js'
import { useUserSecurityStore } from '../../store/user/security.js'

const emit = defineEmits(['back'])

const messageStore = useMessageStore()
const securityStore = useUserSecurityStore()

const content = ref('')
const messagesContainer = ref(null)

const otherParticipant = computed(() => {
  if (!messageStore.currentThread) return null
  return messageStore.getOtherParticipant(messageStore.currentThread)
})

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

  try {
    await messageStore.postMessageInThread({
      threadId: messageStore.currentThreadId,
      content: content.value
    })
    content.value = ''
    scrollToBottom()
  } catch (e) {
    console.error('Failed to send message:', e)
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

// Scroll to bottom when messages change
watch(() => messageStore.messages, () => {
  scrollToBottom()
}, { deep: true })

// Scroll to bottom when loading finishes
watch(() => messageStore.isLoadingMessages, (isLoading) => {
  if (!isLoading) {
    scrollToBottom()
  }
})
</script>
