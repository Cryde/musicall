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

      <!-- A contenteditable rather than a textarea, so a mention can be a chip that reads as a name.
           `whitespace-pre-wrap` is what makes a plain "\n" render, which keeps newlines out of the
           serializer's way: no <br> bookkeeping, Shift+Enter just inserts a character. -->
      <div
        ref="editor"
        :contenteditable="!chatStore.isSending"
        role="textbox"
        aria-multiline="true"
        aria-label="Votre message"
        data-placeholder="Votre message..."
        class="chat-composer-editor flex-1 min-h-[3.5rem] max-h-40 overflow-y-auto whitespace-pre-wrap break-words rounded-md border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-950 px-3 py-2 text-sm focus:outline-none focus:border-primary-500"
        :class="{ 'is-empty': isEmpty }"
        @input="handleInput"
        @keydown="handleKeydown"
        @paste="handlePaste"
        @drop="handleDrop"
        @blur="showSuggestions = false"
        @compositionstart="isComposing = true"
        @compositionend="handleCompositionEnd"
      />
      <Button
        icon="pi pi-send"
        aria-label="Envoyer le message"
        :loading="chatStore.isSending"
        :disabled="isEmpty || chatStore.isSending"
        @click="send"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, nextTick, ref } from 'vue'
import { useMentionParser } from '../../../composables/useMentionParser.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'
import { MENTION_ID_ATTRIBUTE, serializeEditor } from '../../../utils/mentionEditor.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  members: { type: Array, default: () => [] }
})

const emit = defineEmits(['sent'])

const chatStore = useBandSpaceChatStore()
const { findMentionQuery, getSuggestions } = useMentionParser()

const editor = ref(null)
/** What gets sent: the composer's content in the stored `@[uuid]` format, never what is on screen. */
const wireContent = ref('')
const sendError = ref('')
const isEmpty = computed(() => wireContent.value.trim() === '')

const suggestions = ref([])
const showSuggestions = ref(false)
const selectedIndex = ref(0)
// Not a ref: nothing renders from it, and an IME must not make the dropdown flicker mid-word.
let isComposing = false

/**
 * `@tous` rides the roster as a pretend member, so it is picked and chipped like anybody else.
 * Must match ChatMentionResolver::EVERYONE_TOKEN on the server.
 */
const EVERYONE_ID = 'tous'
const EVERYONE_MEMBER = { user_id: EVERYONE_ID, username: 'tous' }

const suggestionSource = computed(() => [EVERYONE_MEMBER, ...props.members])

/** Tailwind sees these as literal strings and generates them, the same as any class in the template. */
const CHIP_CLASS =
  'chat-mention-chip inline-block rounded px-1 font-semibold bg-primary-100 text-primary-700 dark:bg-primary-500/25 dark:text-primary-200'

function syncContent() {
  wireContent.value = editor.value ? serializeEditor(editor.value) : ''
}

/**
 * The caret, but only when it is a plain insertion point inside a text node of this editor. Anything
 * else, a selection spanning nodes or a caret parked next to a chip, is not somewhere a mention can
 * be being typed.
 */
function caretInText() {
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) {
    return null
  }

  const range = selection.getRangeAt(0)
  const container = range.startContainer
  if (container.nodeType !== Node.TEXT_NODE || !editor.value?.contains(container)) {
    return null
  }

  return { node: container, offset: range.startOffset }
}

function updateSuggestions() {
  const caret = caretInText()
  const query = caret === null ? null : findMentionQuery(caret.node.data.slice(0, caret.offset))
  if (query === null) {
    showSuggestions.value = false

    return
  }

  suggestions.value = getSuggestions(query, suggestionSource.value)
  showSuggestions.value = suggestions.value.length > 0
  selectedIndex.value = 0
}

function handleInput() {
  syncContent()
  // Mid-composition the text is provisional, so offering to complete it would fight the IME.
  if (!isComposing) {
    updateSuggestions()
  }
}

function handleCompositionEnd() {
  isComposing = false
  handleInput()
}

function selectSuggestion(member) {
  const caret = caretInText()
  if (!caret || !member) {
    return
  }

  const before = caret.node.data.slice(0, caret.offset)
  const query = findMentionQuery(before)
  if (query === null) {
    return
  }

  // Select the `@query` being typed, `@` included, so the command below replaces it.
  const range = document.createRange()
  range.setStart(caret.node, before.length - query.length - 1)
  range.setEnd(caret.node, caret.offset)
  const selection = window.getSelection()
  selection.removeAllRanges()
  selection.addRange(range)

  // The trailing space is not decoration: it gives the caret somewhere to live after an atomic chip,
  // and separates this mention from the next word. `whitespace-pre-wrap` keeps it from collapsing.
  //
  // `execCommand` for the same reason insertText uses it, and it took a measurement to notice: doing
  // this with Range surgery inserts an identical chip and silently empties the undo stack, so Ctrl+Z
  // afterwards did nothing at all, not even to text typed before the mention. `outerHTML` of a node
  // built with `textContent` rather than a hand-built string, so the username is escaped by the DOM.
  const chipHtml = `${createChip(member).outerHTML} `
  if (!document.execCommand('insertHTML', false, chipHtml)) {
    range.deleteContents()
    const spacer = document.createTextNode(' ')
    range.insertNode(spacer)
    range.insertNode(createChip(member))
    placeCaretAtEnd(spacer)
  }

  showSuggestions.value = false
  syncContent()
}

function createChip(member) {
  const chip = document.createElement('span')
  chip.className = CHIP_CLASS
  // Atomic: the browser treats it as one object to step over and to delete.
  chip.contentEditable = 'false'
  chip.dataset[MENTION_ID_ATTRIBUTE] = member.user_id
  chip.textContent = `@${member.username}`

  return chip
}

function placeCaretAtEnd(textNode) {
  const range = document.createRange()
  range.setStart(textNode, textNode.length)
  range.collapse(true)

  const selection = window.getSelection()
  selection.removeAllRanges()
  selection.addRange(range)
}

function handleKeydown(event) {
  if (showSuggestions.value && handleSuggestionKey(event)) {
    return
  }

  if (event.key === 'Enter') {
    event.preventDefault()
    if (event.shiftKey) {
      insertText('\n')

      return
    }
    if (!event.ctrlKey && !event.metaKey && !event.altKey) {
      send()
    }

    return
  }

  if (event.key === 'Backspace') {
    handleBackspace(event)
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

/**
 * Backspace against a chip removes the whole mention rather than a letter of the name it renders.
 *
 * `contenteditable="false"` already makes browsers treat a chip as one object, but not identically:
 * some select it first and delete on the second press. Doing it here makes one press mean one thing
 * everywhere, which matters for something people delete by feel.
 */
function handleBackspace(event) {
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0 || !selection.isCollapsed) {
    return
  }

  const chip = chipBefore(selection.getRangeAt(0))
  if (!chip) {
    return
  }

  event.preventDefault()
  chip.remove()
  syncContent()
  updateSuggestions()
}

function chipBefore(range) {
  const { startContainer, startOffset } = range
  if (startContainer.nodeType === Node.TEXT_NODE) {
    return startOffset === 0 ? asChip(startContainer.previousSibling) : null
  }

  return startOffset === 0 ? null : asChip(startContainer.childNodes[startOffset - 1])
}

function asChip(node) {
  return node?.dataset?.[MENTION_ID_ATTRIBUTE] ? node : null
}

/**
 * Paste lands as plain text, always. A contenteditable will happily accept a whole styled document
 * otherwise, and the serializer would then have to decide what somebody's pasted table meant.
 */
function handlePaste(event) {
  event.preventDefault()
  insertText(event.clipboardData?.getData('text/plain') ?? '')
}

/**
 * A drop is a second way in, and hardening only the paste left it open.
 *
 * Dropping a fragment from another tab inserts it verbatim, so a span carrying `data-mention-id`
 * arrives as a working chip whose label need not match the member it means. The server still refuses
 * to notify anybody who is not an active member, and the renderer names them from the stored rows
 * rather than from the label, so nothing false reaches a reader; what it costs is the composer's own
 * promise that a chip says who it means. Plain text only, exactly like a paste.
 */
function handleDrop(event) {
  event.preventDefault()
  insertText(event.dataTransfer?.getData('text/plain') ?? '')
}

/**
 * `execCommand`, deprecated and still the only right answer here.
 *
 * Building the text node by hand and moving the caret onto it looks equivalent and is not: the
 * browser merges adjacent text nodes in a contenteditable, which orphans the reference and drops the
 * caret somewhere else. Measured, by typing after a Shift+Enter and watching the newline end up at the
 * end of the line instead of where it was typed. `insertText` also keeps native undo working, which
 * hand-built DOM mutation does not.
 *
 * The manual path stays as a fallback for a browser that refuses the command, where a caret in the
 * wrong place beats losing what was typed.
 */
function insertText(value) {
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0) {
    return
  }

  // Both ends, not just the anchor: a selection dragged out of the composer and over the messages
  // above it would otherwise pass the guard, and the fallback below would delete what it spans.
  const selected = selection.getRangeAt(0)
  if (
    !editor.value?.contains(selected.startContainer) ||
    !editor.value.contains(selected.endContainer)
  ) {
    return
  }

  if (!document.execCommand('insertText', false, value)) {
    const range = selected
    range.deleteContents()
    const node = document.createTextNode(value)
    range.insertNode(node)
    range.setStartAfter(node)
    range.collapse(true)
    selection.removeAllRanges()
    selection.addRange(range)
  }

  syncContent()
  updateSuggestions()
}

async function send() {
  const content = wireContent.value
  if (content.trim() === '' || chatStore.isSending) {
    return
  }

  sendError.value = ''

  try {
    await chatStore.sendMessage(props.bandSpaceId, content)
    clearEditor()
    emit('sent')
    nextTick(() => editor.value?.focus())
  } catch (e) {
    sendError.value = errorMessageFor(e)
  }
}

function clearEditor() {
  editor.value?.replaceChildren()
  wireContent.value = ''
  showSuggestions.value = false
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

<style>
/* Not scoped: the chips are created in script, so they never get the scoping attribute. The
   placeholder is driven by a class rather than `:empty`, because a contenteditable that has been
   typed in and emptied again is rarely truly empty. */
.chat-composer-editor.is-empty::before {
  content: attr(data-placeholder);
  color: var(--p-surface-400);
  pointer-events: none;
}
</style>
