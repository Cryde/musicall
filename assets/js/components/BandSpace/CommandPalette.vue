<template>
  <Dialog
    v-model:visible="visible"
    modal
    dismissableMask
    :show-header="false"
    :style="{ width: '38rem' }"
    :breakpoints="{ '40rem': '95vw' }"
    class="overflow-hidden!"
    content-class="p-0!"
    :pt="{ root: { 'aria-labelledby': HEADING_ID } }"
    @show="reset"
  >
    <!-- Own header rather than the Dialog's, so the input is the whole top row. PrimeVue points
         aria-labelledby at a header element that no longer exists, hence the redirection above. -->
    <h2 :id="HEADING_ID" class="sr-only">Rechercher dans le Band Space</h2>

    <div class="flex w-full items-center px-4 py-3">
      <IconField class="flex-1">
        <InputIcon class="pi pi-search text-surface-500 dark:text-surface-300" />
        <InputText
          v-model="query"
          autofocus
          class="w-full border-0! shadow-none! outline-0!"
          role="combobox"
          aria-autocomplete="list"
          aria-label="Rechercher dans le Band Space"
          :aria-expanded="flatResults.length > 0"
          :aria-controls="flatResults.length > 0 ? listboxId : undefined"
          :aria-activedescendant="activeOptionId"
          placeholder="Rechercher dans cet espace..."
          @keydown.down.prevent="moveActive(1)"
          @keydown.up.prevent="moveActive(-1)"
          @keydown.enter.prevent="openActiveResult"
        />
      </IconField>
    </div>

    <div class="border-t border-surface p-4">
      <BandSpaceSearchResults
        :groups="groups"
        :active-result="activeResult"
        :query="query"
        :is-searching="isSearching"
        :search-error="searchError"
        :has-searched="hasSearched"
        :listbox-id="listboxId"
        label="Résultats de la recherche"
        @select="openResult"
        @activate="setActiveResult"
      />
    </div>

    <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-surface bg-surface-50 dark:bg-surface-900">
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center gap-1 text-xs text-surface-600 dark:text-surface-300">
          <kbd :class="KBD_CLASS">⏎</kbd> Ouvrir
        </span>
        <span class="inline-flex items-center gap-1 text-xs text-surface-600 dark:text-surface-300">
          <kbd :class="KBD_CLASS">↑</kbd><kbd :class="KBD_CLASS">↓</kbd> Naviguer
        </span>
        <span class="inline-flex items-center gap-1 text-xs text-surface-600 dark:text-surface-300">
          <kbd :class="KBD_CLASS">esc</kbd> Fermer
        </span>
      </div>
      <!-- Visible and announced: the count is the one thing a screen reader cannot infer from the
           list appearing, and sighted users want it too. -->
      <span class="text-xs text-surface-600 dark:text-surface-300 tabular-nums" aria-live="polite">
        {{ resultCountLabel }}
      </span>
    </div>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { computed, useId } from 'vue'
import { useRouter } from 'vue-router'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { useBandSpaceSearch } from '../../composables/useBandSpaceSearch.js'
import { routeForResult } from '../../utils/bandSpaceSearch.js'
import BandSpaceSearchResults from './BandSpaceSearchResults.vue'

// A #header slot replaces the element PrimeVue points aria-labelledby at, which would leave the
// dialog with no accessible name at all, so the reference is redirected at the heading below.
const HEADING_ID = 'band-space-search-heading'
const KBD_CLASS =
  'font-sans p-1 min-w-5 inline-flex items-center justify-center rounded-md leading-none border border-surface-300 dark:border-surface-600 bg-surface-100 dark:bg-surface-800'

const visible = defineModel('visible', { type: Boolean, default: false })

const router = useRouter()
const { currentSpaceId } = useBandSpaceNavigation()

// Per instance, because the palette and the attachment picker each own a listbox and two elements
// cannot share a DOM id.
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
} = useBandSpaceSearch(currentSpaceId, listboxId)

const resultCountLabel = computed(() => {
  if (!hasSearched.value || isSearching.value) {
    return ''
  }
  const count = flatResults.value.length
  if (count === 0) {
    return 'Aucun résultat'
  }

  return count === 1 ? '1 résultat' : `${count} résultats`
})

function openActiveResult() {
  if (activeResult.value) {
    openResult(activeResult.value)
  }
}

function openResult(result) {
  const target = routeForResult(result, currentSpaceId.value)
  if (!target) {
    return
  }

  visible.value = false
  router.push(target)
}
</script>
