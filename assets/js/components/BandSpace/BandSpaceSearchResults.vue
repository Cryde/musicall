<template>
  <!-- One kind at a time, or all of them (#1046). Buttons, so Tab reaches them while the arrow keys
       stay with the caller's input and the rows below. -->
  <div class="flex flex-wrap gap-2 mb-3" role="group" :aria-label="t('band_space.search.filter_by_type')">
    <button
      v-for="searchType in SEARCH_TYPES"
      :key="searchType.type"
      type="button"
      :aria-pressed="selectedType === searchType.type"
      :class="[
        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs border transition-colors',
        selectedType === searchType.type
          ? 'border-primary-700 bg-primary-700 text-white dark:border-primary-300 dark:bg-primary-300 dark:text-surface-900'
          : 'border-transparent bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-200 hover:bg-surface-200 dark:hover:bg-surface-700'
      ]"
      @click="emit('toggle-type', searchType.type)"
    >
      <i :class="['pi', searchType.icon, 'text-xs']" aria-hidden="true" />
      <span>{{ searchType.label }}</span>
    </button>
    <!-- The order of the list shown before typing (#1047): one Tab stop after the kinds, and gone
         once a term is typed, since the hits are grouped by kind whatever the order. -->
    <button
      v-if="isQueryShort"
      type="button"
      :aria-pressed="isOpenedOrder"
      :class="[
        'flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs border transition-colors sm:ml-auto',
        isOpenedOrder
          ? 'border-primary-700 bg-primary-700 text-white dark:border-primary-300 dark:bg-primary-300 dark:text-surface-900'
          : 'border-surface-300 dark:border-surface-600 text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-800'
      ]"
      @click="emit('toggle-order')"
    >
      <i class="pi pi-eye text-xs" aria-hidden="true" />
      <span>{{ t('band_space.search.opened_by_me') }}</span>
    </button>
  </div>

  <div v-if="isSearching" class="py-8 flex justify-center">
    <ProgressSpinner style="width: 2rem; height: 2rem" />
  </div>

  <Message v-else-if="searchError" severity="error" :closable="false">
    {{ searchError }}
  </Message>

  <!-- No query yet: what the band touched last, or what the member opened last (#1047), newest first,
       and flat, so the date order, which is the whole point, survives (#1046). The same listbox and rows
       as the hits, so the arrow keys and the screen reader treat both alike. -->
  <div v-else-if="!hasSearched" class="flex flex-col gap-2">
    <template v-if="recents.length > 0">
      <p class="flex items-center gap-2 px-2 m-0 text-xs font-semibold uppercase tracking-wide text-surface-600 dark:text-surface-300">
        <i class="pi pi-history text-xs" aria-hidden="true" />
        {{ recentsHeading }}
      </p>
      <div
        :id="listboxId"
        ref="listboxRef"
        role="listbox"
        :aria-label="t('band_space.search.recents_listbox', { label, heading: recentsHeading.toLowerCase() })"
        class="flex flex-col max-h-[24rem] overflow-y-auto"
      >
        <BandSpaceSearchResultRow
          v-for="result in recents"
          :key="result.id"
          :result="result"
          :listbox-id="listboxId"
          :is-active="result.id === activeResult?.id"
          :unselectable="isUnselectable(result)"
          :picked="isPicked(result)"
          :picked-label="pickedLabel"
          show-kind
          @choose="choose"
          @activate="emit('activate', $event)"
        />
      </div>
    </template>
    <p v-if="!isLoadingRecents" class="text-sm text-surface-600 dark:text-surface-300 m-0">
      {{ t('band_space.search.min_length', { min: MIN_SEARCH_QUERY_LENGTH }) }}
    </p>
  </div>

  <p v-else-if="groups.length === 0" class="py-6 m-0 text-center text-surface-600 dark:text-surface-300">
    {{ t('band_space.search.no_result_for', { query: query.trim() }) }}
  </p>

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
      <BandSpaceSearchResultRow
        v-for="result in group.results"
        :key="result.id"
        :result="result"
        :listbox-id="listboxId"
        :is-active="result.id === activeResult?.id"
        :unselectable="isUnselectable(result)"
        :picked="isPicked(result)"
        :picked-label="pickedLabel"
        :term="query.trim()"
        @choose="choose"
        @activate="emit('activate', $event)"
      />
    </div>
  </div>
</template>

<script setup>
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { MIN_SEARCH_QUERY_LENGTH, RECENTS_ORDER } from '../../composables/useBandSpaceSearch.js'
import { SEARCH_TYPES } from '../../utils/bandSpaceSearch.js'
import BandSpaceSearchResultRow from './BandSpaceSearchResultRow.vue'

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
  /** The space's latest items, newest first, shown while nothing is typed (#1046). */
  recents: { type: Array, default: () => [] },
  isLoadingRecents: { type: Boolean, default: false },
  /** The one kind the member narrowed to, or null for every kind. */
  selectedType: { type: String, default: null },
  /** How the recents are sorted, a RECENTS_ORDER value. */
  order: { type: String, default: RECENTS_ORDER.UPDATED },
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

const emit = defineEmits(['select', 'activate', 'toggle-type', 'toggle-order'])
const { t } = useI18n()

const listboxRef = ref(null)

const isQueryShort = computed(() => props.query.trim().length < MIN_SEARCH_QUERY_LENGTH)
const isOpenedOrder = computed(() => props.order === RECENTS_ORDER.OPENED)
// A short history is topped up with the band's latest changes, so the list starts with what was
// opened rather than holding only that, and the heading says so.
const recentsHeading = computed(() =>
  isOpenedOrder.value ? t('band_space.search.recents_opened_first') : t('band_space.search.recents')
)

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

// The lists too, not only the active row: a new search leaves the active index at 0, so without this
// the list would keep the scroll position of the previous one and open below the active row.
watch([() => props.activeResult, () => props.groups, () => props.recents], async () => {
  await nextTick()
  listboxRef.value?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' })
})
</script>
