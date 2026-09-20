<template>
  <Dialog
    v-model:visible="visible"
    modal
    dismissableMask
    header="Joindre un élément du Band Space"
    :style="{ width: '38rem' }"
    :breakpoints="{ '40rem': '95vw' }"
    :close-button-props="{ 'aria-label': 'Fermer' }"
    content-class="p-0!"
    @show="reset"
  >
    <div class="flex w-full items-center px-4 pt-1 pb-3">
      <IconField class="flex-1">
        <InputIcon class="pi pi-search text-surface-500 dark:text-surface-300" />
        <InputText
          ref="searchInput"
          v-model="query"
          autofocus
          class="w-full"
          role="combobox"
          aria-autocomplete="list"
          aria-label="Rechercher un élément à joindre"
          :aria-expanded="flatResults.length > 0"
          :aria-controls="flatResults.length > 0 ? listboxId : undefined"
          :aria-activedescendant="activeOptionId"
          placeholder="Agenda, tâche, note, fichier, setlist..."
          @keydown.down.prevent="moveActive(1)"
          @keydown.up.prevent="moveActive(-1)"
          @keydown.enter.prevent="pickActiveResult"
        />
      </IconField>
    </div>

    <div class="border-t border-surface p-4">
      <!-- Before the list rather than after: the reason the rows stopped responding has to be read
           before they are clicked, not once nothing happens. -->
      <Message v-if="isFull" severity="warn" :closable="false" class="mb-3">
        {{ FULL_DRAFT_MESSAGE }} Retirez-en un pour en joindre un autre.
      </Message>

      <BandSpaceSearchResults
        :groups="groups"
        :active-result="activeResult"
        :query="query"
        :is-searching="isSearching"
        :search-error="searchError"
        :has-searched="hasSearched"
        :listbox-id="listboxId"
        :picked-ids="pickedIds"
        :disabled="isFull"
        label="Éléments à joindre"
        picked-label="Joint"
        @select="pick"
        @activate="setActiveResult"
      />
    </div>

    <template #footer>
      <div class="flex w-full items-center justify-between gap-3">
        <span class="text-xs text-surface-600 dark:text-surface-300 tabular-nums" aria-live="polite">
          {{ pickedIds.length }} / {{ MAX_CHAT_ATTACHMENTS }} joints
        </span>
        <Button label="Fermer" severity="secondary" text @click="visible = false" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { computed, nextTick, ref, useId } from 'vue'
import { useBandSpaceSearch } from '../../../composables/useBandSpaceSearch.js'
import { FULL_DRAFT_MESSAGE, MAX_CHAT_ATTACHMENTS } from '../../../utils/chatAttachmentDraft.js'
import BandSpaceSearchResults from '../BandSpaceSearchResults.vue'

/**
 * The command palette's search in a different container: it writes a reference into the composer
 * instead of navigating to what it found (#971). Same endpoint, same grouping, same keyboard, which
 * is why nothing here knows how a task differs from a setlist.
 *
 * It stays open after a pick. The cap is five, so picking more than one is the expected case, and
 * the row the member just took says « Joint » where they are already looking.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** The composer's draft, so a reference already taken cannot be taken twice. */
  pickedIds: { type: Array, default: () => [] }
})

const emit = defineEmits(['pick'])

const visible = defineModel('visible', { type: Boolean, default: false })

const searchInput = ref(null)

// Per instance, because the palette and this picker each own a listbox and two elements cannot
// share a DOM id.
const listboxId = useId()

const {
  activeOptionId,
  activeResult,
  flatResults,
  groups,
  hasSearched,
  isSearching,
  moveActive,
  query,
  reset,
  searchError,
  setActiveResult
} = useBandSpaceSearch(() => props.bandSpaceId, listboxId)

const isFull = computed(() => props.pickedIds.length >= MAX_CHAT_ATTACHMENTS)

function pickActiveResult() {
  if (activeResult.value) {
    pick(activeResult.value)
  }
}

/**
 * The term is deliberately left alone: one search often turns up two things worth pointing at, and
 * clearing it would make the second one a retype. Focus goes back to the input because a click on a
 * row takes it away, and the next thing the member does is type.
 *
 * The two refusals are checked here and not only on the row, because a disabled option stays in the
 * arrow keys' path, as it should: the member can land on a « Joint » row and press Enter. The
 * composer would refuse it anyway, but its warning renders behind this dialog's mask, so the only
 * honest place to stop is before it is emitted.
 */
function pick(result) {
  if (isFull.value || props.pickedIds.includes(result.id)) {
    return
  }

  emit('pick', result)
  nextTick(() => searchInput.value?.$el?.focus())
}
</script>
