<template>
  <div class="relative">
    <ul
      v-if="showSuggestions"
      :id="listboxId"
      role="listbox"
      aria-label="Suggestions de mention"
      class="absolute bottom-full left-0 mb-1 w-56 max-h-40 overflow-y-auto z-50 rounded-lg shadow-lg bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 list-none m-0 p-0"
    >
      <li
        v-for="(member, index) in suggestions"
        :id="optionId(index)"
        :key="member.user_id"
        role="option"
        :aria-selected="index === selectedIndex"
        tabindex="-1"
        class="flex items-center gap-2 w-full px-3 py-2 text-left text-sm cursor-pointer"
        :class="
          index === selectedIndex
            ? 'bg-surface-100 dark:bg-surface-700'
            : 'hover:bg-surface-50 dark:hover:bg-surface-700/50'
        "
        @mousedown.prevent="selectSuggestion(member)"
      >
        <span
          class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-200 text-xs font-semibold shrink-0"
          aria-hidden="true"
        >
          {{ member.username.charAt(0).toUpperCase() }}
        </span>
        <span class="truncate">{{ member.username }}</span>
        <span v-if="member.hint" class="ml-auto text-xs text-surface-500">{{ member.hint }}</span>
      </li>
    </ul>

    <!-- A contenteditable rather than a textarea, so a mention can be a chip that reads as a name.
         `whitespace-pre-wrap` is what makes a plain "\n" render, which keeps newlines out of the
         serializer's way: no <br> bookkeeping, Shift+Enter just inserts a character.

         `role="textbox"` and not `combobox`, which does not support `aria-multiline`. The three
         combobox-ish attributes below are all valid on a textbox, and they are what makes the arrow
         keys mean something to a screen reader rather than moving a highlight only sighted users see. -->
    <div
      ref="editor"
      :contenteditable="!disabled"
      role="textbox"
      aria-multiline="true"
      :aria-label="ariaLabel"
      :aria-placeholder="placeholder || undefined"
      :aria-expanded="showSuggestions"
      :aria-controls="showSuggestions ? listboxId : undefined"
      :aria-activedescendant="showSuggestions ? optionId(selectedIndex) : undefined"
      :data-placeholder="placeholder"
      class="mention-editor w-full overflow-y-auto whitespace-pre-wrap break-words border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-950 text-sm focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary-500"
      :class="[editorClass, { 'is-empty': isEmpty }]"
      @input="handleInput"
      @keydown="handleKeydown"
      @paste="handlePaste"
      @drop="handleDrop"
      @blur="showSuggestions = false"
      @compositionstart="isComposing = true"
      @compositionend="handleCompositionEnd"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, useId, watch } from 'vue'
import { useMentionParser } from '../../composables/useMentionParser.js'
import {
  buildEditorNodes,
  MENTION_ID_ATTRIBUTE,
  serializeEditor
} from '../../utils/mentionEditor.js'

/** What the caller reads and writes: the stored `@[uuid]` format, never what is on screen. */
const content = defineModel({ type: String, default: '' })

const props = defineProps({
  /**
   * The roster to suggest from, supplied by the caller rather than reached for. The chat prepends its
   * `@tous` pseudo member; a task comment must not, because the server only ever extracts uuids from
   * one. It is legitimately empty while the band space settings store is still loading it.
   */
  members: { type: Array, default: () => [] },
  /** Required: a `::before` placeholder is not an accessible name, so nothing else supplies one. */
  ariaLabel: { type: String, required: true },
  placeholder: { type: String, default: '' },
  /** Per-surface sizing and shape, so each box keeps the proportions it had. */
  editorClass: { type: String, default: '' },
  /** Which chord sends: the chat sends on Enter, a comment is a paragraph so it sends on Ctrl+Enter. */
  submitOn: {
    type: String,
    default: 'enter',
    validator: (v) => ['enter', 'ctrl-enter'].includes(v)
  },
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['submit'])

const { findMentionQuery, getSuggestions, parseToParts } = useMentionParser()

const editor = ref(null)
const isEmpty = computed(() => content.value.trim() === '')

const suggestions = ref([])
const showSuggestions = ref(false)
const selectedIndex = ref(0)
// Not a ref: nothing renders from it, and an IME must not make the dropdown flicker mid-word.
let isComposing = false

const listboxId = useId()
const optionId = (index) => `${listboxId}-option-${index}`

/** Tailwind sees these as literal strings and generates them, the same as any class in the template. */
const CHIP_CLASS =
  'mention-editor-chip inline-block rounded px-1 font-semibold bg-primary-100 text-primary-700 dark:bg-primary-500/25 dark:text-primary-200'

const NODE_FACTORIES = {
  text: (value) => document.createTextNode(value),
  chip: (part) => createChip({ user_id: part.userId, username: part.username })
}

onMounted(() => render(content.value))

/**
 * Only when the model says something the editor is not already showing. Without that test every
 * keystroke would rebuild the DOM and throw the caret away; with it the parent can still clear the box
 * after a submit and seed it when an edit box opens.
 */
watch(content, (value) => {
  if (value !== serialized()) {
    render(value)
  }
})

function render(value) {
  const target = editor.value
  if (!target) {
    return
  }

  target.replaceChildren(...buildEditorNodes(parseToParts(value, props.members), NODE_FACTORIES))
  showSuggestions.value = false
  // Back to the caller, because what the DOM settled on is the truth from here on. A seed that
  // normalized to something else has to be what "has this been edited" is measured against.
  syncContent()
}

function serialized() {
  return editor.value ? serializeEditor(editor.value) : ''
}

function syncContent() {
  content.value = serialized()
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

  suggestions.value = getSuggestions(query, props.members)
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
    handleEnter(event)

    return
  }

  if (event.key === 'Backspace') {
    handleBackspace(event)
  }
}

/**
 * One rule: Enter sends when it is this surface's chord, and otherwise makes a newline.
 *
 * Never falls through to the browser, which would answer with a `<div>` or a `<br>` and put the
 * common path at the mercy of the block heuristics the serializer keeps for pasted markup.
 *
 * Note the dropdown is handled before this, so while it is open Enter picks a suggestion whatever the
 * modifiers say. On the Ctrl+Enter surface that means the chord only sends once the list is closed.
 */
function handleEnter(event) {
  event.preventDefault()

  if (isSubmitChord(event)) {
    emit('submit')

    return
  }

  insertText('\n')
}

function isSubmitChord(event) {
  if (props.submitOn === 'ctrl-enter') {
    return event.ctrlKey || event.metaKey
  }

  return !event.shiftKey && !event.ctrlKey && !event.metaKey && !event.altKey
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
    // Stopped here, or it keeps going. PrimeVue's Drawer and Dialog listen for Escape on `document`,
    // so dismissing the suggestion list would also close the task drawer and take the draft with it.
    event.preventDefault()
    event.stopPropagation()
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
    return false
  }

  // Both ends, not just the anchor: a selection dragged out of the editor and over the messages
  // above it would otherwise pass the guard, and the fallback below would delete what it spans.
  const selected = selection.getRangeAt(0)
  if (
    !editor.value?.contains(selected.startContainer) ||
    !editor.value.contains(selected.endContainer)
  ) {
    return false
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

  return true
}

function focus() {
  editor.value?.focus()
}

/** Putting the caret at the end is what an edit box wants: you reopen it to add, not to overwrite. */
function focusAtEnd() {
  const target = editor.value
  if (!target) {
    return
  }

  target.focus()
  const range = document.createRange()
  range.selectNodeContents(target)
  range.collapse(false)
  const selection = window.getSelection()
  selection.removeAllRanges()
  selection.addRange(range)
}

/**
 * Disabling a contenteditable takes the caret away and the browser never gives it back: focus falls
 * to `<body>`, and because an unfocused editor looks exactly like a focused empty one, the next thing
 * typed lands nowhere with nothing on screen to say why. Only a click recovers it.
 *
 * That matters on the failure path, which is the one that leaves text in the box worth carrying on
 * with. The caret goes back where it was rather than to the end, since the content is untouched by a
 * request that failed and finding your place again is the whole cost being avoided.
 *
 * Nothing is restored when the content changed while disabled, which is what a success looks like:
 * the nodes the range pointed into are gone, and a caller that wants focus after a send asks for it.
 */
let rangeWhenDisabled = null

watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) {
      rangeWhenDisabled = ownRange()

      return
    }

    const range = rangeWhenDisabled
    rangeWhenDisabled = null
    if (range) {
      nextTick(() => restoreRange(range))
    }
  }
)

/** The live selection, but only when it is this editor's. */
function ownRange() {
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0) {
    return null
  }

  const range = selection.getRangeAt(0)

  return editor.value?.contains(range.startContainer) ? range : null
}

function restoreRange(range) {
  const target = editor.value
  if (!target?.contains(range.startContainer)) {
    return
  }

  target.focus()
  const selection = window.getSelection()
  selection.removeAllRanges()
  selection.addRange(range)
}

// Imperative because focus is: the chat puts it back after a send, an edit box takes it on open.
// insertText too, for a paste the chat composer took over and then has to give back (#972): it
// answers false when the caret is no longer in the editor.
defineExpose({ focus, focusAtEnd, insertText })
</script>

<style>
/* Not scoped: the chips are created in script, so they never get the scoping attribute. The
   placeholder is driven by a class rather than `:empty`, because a contenteditable that has been
   typed in and emptied again is rarely truly empty. */
.mention-editor.is-empty::before {
  content: attr(data-placeholder);
  color: var(--p-surface-400);
  pointer-events: none;
}
</style>
