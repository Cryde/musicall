<template>
  <div class="mt-1 flex flex-wrap items-center gap-1" :class="{ 'justify-end': alignEnd }">
    <button
      v-for="reaction in reactions"
      :key="reaction.key"
      type="button"
      class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs leading-5 transition-colors"
      :class="
        reaction.has_reacted
          ? 'border-primary-700 bg-primary-50 text-primary-800 dark:border-primary-400 dark:bg-primary-950 dark:text-primary-200'
          : 'border-surface-300 bg-surface-100 text-surface-700 hover:bg-surface-200 dark:border-surface-600 dark:bg-surface-700 dark:text-surface-200 dark:hover:bg-surface-600'
      "
      :aria-pressed="reaction.has_reacted"
      :aria-label="pillLabel(reaction)"
      @click="handleToggle(reaction.key)"
    >
      <span aria-hidden="true">{{ reaction.emoji }}</span>
      <span aria-hidden="true">{{ reaction.count }}</span>
    </button>

    <!-- Always in the DOM so it stays reachable by keyboard and by a screen reader, and only
         revealed on hover or focus so a quiet conversation is not a column of smileys. -->
    <button
      type="button"
      class="inline-flex h-6 w-6 items-center justify-center rounded-full text-surface-600 transition-opacity hover:bg-surface-200 dark:text-surface-300 dark:hover:bg-surface-600"
      :class="
        isPickerOpen
          ? 'opacity-100'
          : 'opacity-0 focus-visible:opacity-100 group-hover/message:opacity-100'
      "
      aria-label="Ajouter une réaction"
      aria-haspopup="dialog"
      :aria-expanded="isPickerOpen"
      @click="handleOpenPicker"
    >
      <i class="pi pi-face-smile text-sm" aria-hidden="true" />
    </button>

    <Popover ref="pickerRef" @show="isPickerOpen = true" @hide="isPickerOpen = false">
      <div class="flex gap-1" role="group" aria-label="Choisir une réaction">
        <button
          v-for="choice in CHAT_REACTIONS"
          :key="choice.key"
          type="button"
          class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-lg transition-colors hover:bg-surface-100 dark:hover:bg-surface-700"
          :aria-label="choice.label"
          :aria-pressed="isHeld(choice.key)"
          @click="handlePick(choice.key)"
        >
          <span aria-hidden="true">{{ choice.emoji }}</span>
        </button>
      </div>
    </Popover>
  </div>
</template>

<script setup>
import Popover from 'primevue/popover'
import { ref } from 'vue'
import { CHAT_REACTIONS, chatReactionLabel } from '../../../constants/chatReactions.js'
import { useBandSpaceChatStore } from '../../../store/bandSpace/bandSpaceChat.js'
import { hasReacted } from '../../../utils/chatReactionToggle.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  messageId: { type: String, required: true },
  reactions: { type: Array, default: () => [] },
  /** A message of the viewer's own sits on the right, so its reactions line up under it. */
  alignEnd: { type: Boolean, default: false }
})

const chatStore = useBandSpaceChatStore()
const pickerRef = ref(null)
const isPickerOpen = ref(false)

function isHeld(emojiKey) {
  return hasReacted(props.reactions, emojiKey)
}

/**
 * An emoji carries no accessible name of its own, and a PrimeVue tooltip does not give one either
 * (#688), so the whole sentence goes in the label.
 */
function pillLabel(reaction) {
  const name = chatReactionLabel(reaction.key)
  const action = reaction.has_reacted ? 'retirer ma réaction' : 'réagir'

  return `${name}, ${reaction.count} : ${action}`
}

function handleOpenPicker(event) {
  pickerRef.value?.toggle(event)
}

function handleToggle(emojiKey) {
  chatStore.toggleReaction(props.bandSpaceId, props.messageId, emojiKey)
}

function handlePick(emojiKey) {
  handleToggle(emojiKey)
  pickerRef.value?.hide()
}
</script>
