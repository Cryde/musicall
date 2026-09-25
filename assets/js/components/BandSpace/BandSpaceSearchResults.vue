<template>
  <div v-if="isSearching" class="py-8 flex justify-center">
    <ProgressSpinner style="width: 2rem; height: 2rem" />
  </div>

  <Message v-else-if="searchError" severity="error" :closable="false">
    {{ searchError }}
  </Message>

  <!-- No query yet: naming what is searchable is what teaches the feature. -->
  <div v-else-if="!hasSearched" class="flex flex-col gap-3">
    <p class="text-sm text-surface-600 dark:text-surface-300 m-0">
      Tapez au moins {{ MIN_SEARCH_QUERY_LENGTH }} caractères pour chercher dans&nbsp;:
    </p>
    <ul class="list-none p-0 m-0 flex flex-wrap gap-2">
      <li
        v-for="searchType in SEARCH_TYPES"
        :key="searchType.type"
        class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-200"
      >
        <i :class="['pi', searchType.icon, 'text-xs']" aria-hidden="true" />
        <span>{{ searchType.label }}</span>
      </li>
    </ul>
  </div>

  <p v-else-if="groups.length === 0" class="py-6 m-0 text-center text-surface-600 dark:text-surface-300">
    Aucun résultat pour «&nbsp;{{ query.trim() }}&nbsp;»
  </p>

  <!--
    The rows are divs rather than buttons on purpose. This is the ARIA combobox pattern: focus stays
    on the caller's input and aria-activedescendant points at the current row, so the rows must not
    be focusable themselves. Elsewhere in the band space a clickable row should still be a button.
  -->
  <div
    v-else
    :id="listboxId"
    ref="listboxRef"
    role="listbox"
    :aria-label="label"
    class="flex flex-col gap-3 max-h-[24rem] overflow-y-auto"
  >
    <div v-for="group in groups" :key="group.type" role="group" :aria-label="group.label">
      <p class="flex items-center gap-2 px-2 pb-1 m-0 text-xs font-semibold uppercase tracking-wide text-surface-600 dark:text-surface-300">
        <i :class="['pi', group.icon, 'text-xs']" aria-hidden="true" />
        {{ group.label }}
      </p>
      <!-- Pointer move rather than CSS hover, so the mouse and the arrow keys drive the one
           active row instead of lighting up two at once. -->
      <div
        v-for="result in group.results"
        :id="searchOptionId(listboxId, result)"
        :key="result.id"
        role="option"
        :aria-selected="result.id === activeResult?.id"
        :aria-disabled="isUnselectable(result) || undefined"
        :class="[
          'flex items-center gap-3 px-3 py-2 rounded-lg border transition-all',
          isUnselectable(result) ? 'cursor-default opacity-70' : 'cursor-pointer',
          result.id === activeResult?.id
            ? 'border-primary-200 dark:border-primary-700/50 bg-primary-50 dark:bg-primary-800/75 text-primary'
            : 'border-transparent'
        ]"
        @click="choose(result)"
        @mousemove="emit('activate', result)"
      >
        <span class="flex-1 min-w-0 truncate search-hit-title" v-html="highlightedTitle(result)" />
        <span v-if="result.subtitle" class="shrink-0 text-xs text-surface-600 dark:text-surface-300 truncate max-w-[10rem]">
          {{ result.subtitle }}
        </span>
        <!-- Worded, not just ticked: a mark that says nothing is a mark the member has to guess at,
             and this way it is part of the row's accessible name. -->
        <span v-if="isPicked(result)" class="shrink-0 inline-flex items-center gap-1 text-xs font-medium">
          <i class="pi pi-check text-xs" aria-hidden="true" />
          {{ pickedLabel }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { nextTick, ref, watch } from 'vue'
import { MIN_SEARCH_QUERY_LENGTH } from '../../composables/useBandSpaceSearch.js'
import { SEARCH_TYPES, searchOptionId } from '../../utils/bandSpaceSearch.js'
import { highlightTerm } from '../../utils/highlight.js'

/**
 * The band space search panel: what the command palette shows under its input, and what the chat
 * attachment picker (#971) shows under its own. Every state lives here, the spinner and the empty
 * ones included, so the two surfaces cannot drift into saying different things about the same search.
 *
 * Presentational only. The caller owns the search itself, through useBandSpaceSearch.
 */
const props = defineProps({
  /** Already grouped by kind, as useBandSpaceSearch hands them over. */
  groups: { type: Array, required: true },
  /** The row the arrow keys are on, or null. */
  activeResult: { type: Object, default: null },
  query: { type: String, default: '' },
  isSearching: { type: Boolean, default: false },
  searchError: { type: String, default: null },
  hasSearched: { type: Boolean, default: false },
  /** Owned by the caller, which points its input's aria-activedescendant into this list. */
  listboxId: { type: String, required: true },
  label: { type: String, required: true },
  /**
   * Result ids the caller has already taken: marked, and not selectable a second time. Empty for
   * the palette, which navigates rather than collecting anything.
   */
  pickedIds: { type: Array, default: () => [] },
  pickedLabel: { type: String, default: '' },
  /**
   * Nothing more can be taken, whatever the row. The caller says why above the list; the rows only
   * have to stop pretending they are still clickable.
   */
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'activate'])

const listboxRef = ref(null)

function isPicked(result) {
  return props.pickedIds.includes(result.id)
}

function isUnselectable(result) {
  return props.disabled || isPicked(result)
}

function choose(result) {
  if (!isUnselectable(result)) {
    emit('select', result)
  }
}

function highlightedTitle(result) {
  return highlightTerm(result.title, props.query.trim())
}

// The groups too, not only the active row: a new search leaves the active index at 0, so without
// this the list would keep the scroll position of the previous one and open below the active row.
watch([() => props.activeResult, () => props.groups], async () => {
  await nextTick()
  listboxRef.value?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' })
})
</script>

<style scoped>
/* v-html content carries no scope attribute, so the highlight needs :deep to reach it. */
.search-hit-title :deep(mark) {
  background-color: rgba(245, 180, 0, 0.35);
  color: inherit;
  padding: 0 0.1em;
  border-radius: 2px;
}
</style>
