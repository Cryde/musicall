<template>
  <ul
    class="mb-2 flex flex-wrap list-none gap-2 p-0 m-0"
    aria-label="Éléments joints à ce message"
  >
    <li
      v-for="attachment in attachments"
      :key="attachment.id"
      class="flex max-w-full items-center gap-2 rounded-lg border border-surface-300 bg-surface-50 py-1 pl-2.5 pr-1 text-sm dark:border-surface-600 dark:bg-surface-800"
    >
      <i :class="['pi', iconFor(attachment), 'text-xs', 'shrink-0']" aria-hidden="true" />
      <span class="max-w-[14rem] truncate">{{ titleOf(attachment) }}</span>
      <button
        type="button"
        class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full border-0 bg-transparent text-surface-600 hover:bg-surface-200 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:cursor-not-allowed disabled:opacity-50 dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-surface-0"
        :aria-label="`Retirer ${titleOf(attachment)}`"
        :disabled="disabled"
        @click="emit('remove', attachment.id)"
      >
        <i class="pi pi-times text-xs" aria-hidden="true" />
      </button>
    </li>
  </ul>
</template>

<script setup>
import { searchTypeFor } from '../../../utils/bandSpaceSearch.js'

const FALLBACK_ICON = 'pi-link'
const FALLBACK_TITLE = 'cet élément'

/**
 * The composer's pending references, one removable chip each (#971).
 *
 * Its own component rather than a block of ChatComposer's template, because the picker is not the
 * only way in: #972 fills the same list from a pasted Band Space URL, and both producers have to end
 * at the same row of chips.
 */
defineProps({
  /** As chatAttachmentDraft holds them: `{ id, type, title }`. */
  attachments: { type: Array, required: true },
  /** While a send is in flight, since the request already carries these. */
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['remove'])

/** The same mapping the palette and the sent card use, so one object is pictured one way. */
function iconFor(attachment) {
  return searchTypeFor(attachment.type)?.icon ?? FALLBACK_ICON
}

/**
 * A search hit always carries a title, but the draft accepts one without since the server snapshots
 * its own label anyway. Without this the chip would be blank and its button would read « Retirer ».
 */
function titleOf(attachment) {
  return attachment.title || FALLBACK_TITLE
}
</script>
