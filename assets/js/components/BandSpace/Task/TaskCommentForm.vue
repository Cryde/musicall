<template>
  <div class="relative">
    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-2">Commentaires</h4>
    <div class="relative">
      <textarea
        ref="textareaRef"
        v-model="content"
        class="w-full border border-surface-200 dark:border-surface-600 rounded-lg p-3 text-sm bg-surface-0 dark:bg-surface-900 text-surface-800 dark:text-surface-100 resize-none focus:outline-none focus:ring-1 focus:ring-primary"
        rows="3"
        placeholder="Écrire un commentaire..."
        :disabled="isSubmitting"
        @input="handleInput"
        @keydown="handleKeydown"
      ></textarea>

      <!-- Mention suggestions dropdown -->
      <div
        v-if="showSuggestions && suggestions.length > 0"
        class="absolute bottom-full left-0 mb-1 bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-lg shadow-lg max-h-40 overflow-y-auto z-50 w-56"
      >
        <button
          v-for="(member, index) in suggestions"
          :key="member.id"
          class="flex items-center gap-2 w-full px-3 py-2 text-sm text-left hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
          :class="{ 'bg-surface-100 dark:bg-surface-800': index === selectedIndex }"
          @click="selectSuggestion(member)"
        >
          <div
            class="w-6 h-6 rounded-full bg-primary flex items-center justify-center text-primary-contrast text-[10px] font-semibold"
          >
            {{ member.username.charAt(0).toUpperCase() }}
          </div>
          <span>{{ member.username }}</span>
        </button>
      </div>
    </div>
    <div class="flex justify-end mt-2">
      <Button
        label="Envoyer"
        icon="pi pi-send"
        size="small"
        :loading="isSubmitting"
        :disabled="!content.trim()"
        @click="handleSubmit"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { ref } from 'vue'
import { useMentionParser } from '../../../composables/useMentionParser.js'

const props = defineProps({
  members: { type: Array, default: () => [] },
  isSubmitting: { type: Boolean, default: false }
})

const emit = defineEmits(['submit'])

const { getSuggestions, insertMention } = useMentionParser()

const content = ref('')
const textareaRef = ref(null)
const showSuggestions = ref(false)
const suggestions = ref([])
const selectedIndex = ref(0)

function handleInput() {
  const textarea = textareaRef.value
  if (!textarea) return

  const cursorPos = textarea.selectionStart
  const textBefore = content.value.slice(0, cursorPos)
  const atIndex = textBefore.lastIndexOf('@')

  if (
    atIndex !== -1 &&
    !textBefore.slice(atIndex).includes(' ') &&
    !textBefore.slice(atIndex).includes('[')
  ) {
    const query = textBefore.slice(atIndex + 1)
    suggestions.value = getSuggestions(query, props.members)
    showSuggestions.value = suggestions.value.length > 0
    selectedIndex.value = 0
  } else {
    showSuggestions.value = false
  }
}

function handleKeydown(event) {
  if (!showSuggestions.value) {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault()
      handleSubmit()
    }
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    selectedIndex.value = Math.min(selectedIndex.value + 1, suggestions.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
  } else if (event.key === 'Enter' || event.key === 'Tab') {
    event.preventDefault()
    selectSuggestion(suggestions.value[selectedIndex.value])
  } else if (event.key === 'Escape') {
    showSuggestions.value = false
  }
}

function selectSuggestion(member) {
  const textarea = textareaRef.value
  if (!textarea) return

  const cursorPos = textarea.selectionStart
  const result = insertMention(content.value, cursorPos, member)
  content.value = result.text
  showSuggestions.value = false

  // Restore cursor position
  setTimeout(() => {
    textarea.focus()
    textarea.setSelectionRange(result.cursor, result.cursor)
  })
}

function handleSubmit() {
  if (!content.value.trim()) return
  emit('submit', content.value)
  content.value = ''
  showSuggestions.value = false
}
</script>
