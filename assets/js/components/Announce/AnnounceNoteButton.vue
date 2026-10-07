<template>
  <span v-if="text" class="inline-flex">
    <Button
      icon="pi pi-comment"
      text
      rounded
      size="small"
      severity="secondary"
      :aria-label="`Lire la note de ${authorName}`"
      aria-haspopup="dialog"
      :aria-expanded="isOpen"
      @pointerenter="handlePointerEnter"
      @pointerleave="handlePointerLeave"
      @click="handleClick"
    />
    <Popover ref="popover" @show="handleShow" @hide="handleHide">
      <!-- Scrolls rather than growing past the screen: a note has no length limit. Focusable so a note
           opened from the keyboard or by a tap is where focus goes, and Escape brings it back. -->
      <p
        ref="noteText"
        tabindex="-1"
        class="m-0 max-w-xs max-h-60 overflow-y-auto text-sm whitespace-pre-line break-words text-surface-700 dark:text-surface-200 focus:outline-none"
      >{{ text }}</p>
    </Popover>
  </span>
</template>

<script setup>
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import { computed, ref } from 'vue'

/**
 * The note an author left on their announce (#1139), behind a « comment » icon: shown while a mouse
 * rests on it, and kept open by a click or a tap, which is all a touch screen or a keyboard has.
 * Rendered as text, never as HTML.
 */
const props = defineProps({
  note: { type: String, default: null },
  authorName: { type: String, required: true }
})

const popover = ref(null)
const noteText = ref(null)
const isOpen = ref(false)
// Opened on purpose rather than by passing over it, so leaving the icon does not close it.
let pinned = false

const text = computed(() => props.note?.trim() || null)

function handlePointerEnter(event) {
  if (event.pointerType === 'mouse') {
    popover.value?.show(event)
  }
}

function handlePointerLeave(event) {
  if (event.pointerType === 'mouse' && !pinned) {
    popover.value?.hide()
  }
}

function handleClick(event) {
  pinned = !pinned
  if (pinned) {
    popover.value?.show(event)
  } else {
    popover.value?.hide()
  }
}

// Only an open on purpose takes focus: passing the mouse over the icon must not move it.
function handleShow() {
  isOpen.value = true
  if (pinned) {
    noteText.value?.focus()
  }
}

function handleHide() {
  isOpen.value = false
  pinned = false
}
</script>
