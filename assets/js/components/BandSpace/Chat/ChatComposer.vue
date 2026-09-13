<template>
  <div class="border-t border-surface-200 dark:border-surface-700 p-4">
    <Message v-if="sendError" severity="error" :closable="false" class="mb-2">
      {{ sendError }}
    </Message>

    <div class="flex gap-2 items-end relative">
      <div
        v-if="showSuggestions"
        class="absolute bottom-full left-0 mb-1 w-56 max-h-40 overflow-y-auto z-50 rounded-lg shadow-lg bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700"
        aria-label="Suggestions de mention"
      >
        <button
          v-for="(member, index) in suggestions"
          :key="member.user_id"
          type="button"
          class="flex items-center gap-2 w-full px-3 py-2 text-left text-sm"
          :class="
            index === selectedIndex
              ? 'bg-surface-100 dark:bg-surface-700'
              : 'hover:bg-surface-50 dark:hover:bg-surface-700/50'
          "
          @mousedown.prevent="selectSuggestion(member)"
        >
          <span
            class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-200 text-xs font-semibold shrink-0"
          >
            {{ member.username.charAt(0).toUpperCase() }}
          </span>
          <span class="truncate">{{ member.username }}</span>
          <span v-if="member.user_id === EVERYONE_ID" class="ml-auto text-xs text-surface-500">
            tout le groupe
          </span>
        </button>
      </div>

      <Textarea
        ref="messageInput"
        v-model="content"
        rows="2"
        class="flex-1 resize-none"
        placeholder="Votre message..."
        aria-label="Votre message"
        :disabled="chatStore.isSending"
        @input="handleInput"
        @keydown="handleKeydown"
        @blur="showSuggestions = false"
      />
      <Button
        icon="pi pi-send"
        aria-label="Envoyer le message"
        :loading="chatStore.isSending"
        :disabled="!content.trim() || chatStore.isSending"
        @click="send"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { computed, nextTick, ref } from 'vue'
import { useMentionParser } from '../../../composables/useMentionParser.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  members: { type: Array, default: () => [] }
})

const emit = defineEmits(['sent'])

const chatStore = useBandSpaceChatStore()
const { getSuggestions, insertMention } = useMentionParser()
const content = ref('')
const sendError = ref('')
const messageInput = ref(null)

const suggestions = ref([])
const showSuggestions = ref(false)
const selectedIndex = ref(0)

/**
 * `@tous` rides the roster as a pretend member rather than as a branch of its own, so insertMention()
 * writes `@[tous]` with no change to the shared composable and the filtering treats it like anybody
 * else. Its id can never collide with a real one, because a real one is a uuid.
 *
 * Must match ChatMentionResolver::EVERYONE_TOKEN on the server.
 */
const EVERYONE_ID = 'tous'
const EVERYONE_MEMBER = { user_id: EVERYONE_ID, username: 'tous' }

// First, so it is what an arrow-less Enter picks when the query matches it.
const suggestionSource = computed(() => [EVERYONE_MEMBER, ...props.members])

/**
 * Mirrors TaskCommentForm's trigger. The `'['` test is the non-obvious one: without it the caret
 * sitting just after an inserted `@[uuid]` would reopen the dropdown on the uuid itself.
 */
function handleInput() {
  const textarea = messageInput.value?.$el
  if (!textarea) {
    return
  }

  const textBefore = content.value.slice(0, textarea.selectionStart)
  const atIndex = textBefore.lastIndexOf('@')
  const trigger = textBefore.slice(atIndex)

  if (atIndex !== -1 && !trigger.includes(' ') && !trigger.includes('[')) {
    suggestions.value = getSuggestions(trigger.slice(1), suggestionSource.value)
    showSuggestions.value = suggestions.value.length > 0
    selectedIndex.value = 0

    return
  }

  showSuggestions.value = false
}

/**
 * One handler rather than the `@keydown.enter.exact.prevent` this had, because a template modifier
 * cannot be made conditional and Enter now means two different things: pick a suggestion when the
 * dropdown is open, send when it is not. Shift+Enter stays a newline either way.
 */
function handleKeydown(event) {
  if (showSuggestions.value && handleSuggestionKey(event)) {
    return
  }

  if (
    event.key === 'Enter' &&
    !event.shiftKey &&
    !event.ctrlKey &&
    !event.metaKey &&
    !event.altKey
  ) {
    event.preventDefault()
    send()
  }
}

/** @returns {boolean} whether the key belonged to the dropdown and is dealt with */
function handleSuggestionKey(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, suggestions.value.length - 1)

    return true
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)

    return true
  }
  if (event.key === 'Enter' || event.key === 'Tab') {
    event.preventDefault()
    selectSuggestion(suggestions.value[selectedIndex.value])

    return true
  }
  if (event.key === 'Escape') {
    showSuggestions.value = false

    return true
  }

  return false
}

function selectSuggestion(member) {
  const textarea = messageInput.value?.$el
  if (!textarea || !member) {
    return
  }

  const result = insertMention(content.value, textarea.selectionStart, member)
  content.value = result.text
  showSuggestions.value = false

  // After the v-model write has reached the DOM, or the caret lands at the old length.
  setTimeout(() => {
    textarea.focus()
    textarea.setSelectionRange(result.cursor, result.cursor)
  })
}

async function send() {
  if (!content.value.trim() || chatStore.isSending) {
    return
  }

  sendError.value = ''

  try {
    await chatStore.sendMessage(props.bandSpaceId, content.value)
    content.value = ''
    emit('sent')
    nextTick(() => messageInput.value?.$el?.focus())
  } catch (e) {
    sendError.value = errorMessageFor(e)
  }
}

/**
 * handleApiError already carries the server's own French sentence, so a message too long or a space
 * in its deletion grace period explains itself. Only the rate limiter needs wording of our own, since
 * its 429 body says nothing a member could act on.
 */
function errorMessageFor(error) {
  if (error.status === 429) {
    return 'Trop de messages envoyés. Veuillez patienter un instant.'
  }

  console.error('Failed to send the message:', error)

  return error.message || "Le message n'a pas pu être envoyé."
}
</script>
