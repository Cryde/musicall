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
          :aria-controls="flatResults.length > 0 ? LISTBOX_ID : undefined"
          :aria-activedescendant="activeOptionId"
          placeholder="Rechercher dans cet espace..."
          @keydown.down.prevent="moveActive(1)"
          @keydown.up.prevent="moveActive(-1)"
          @keydown.enter.prevent="openActiveResult"
        />
      </IconField>
    </div>

    <div class="border-t border-surface p-4">
      <div v-if="isSearching" class="py-8 flex justify-center">
        <ProgressSpinner style="width: 2rem; height: 2rem" />
      </div>

      <Message v-else-if="searchError" severity="error" :closable="false">
        {{ searchError }}
      </Message>

      <!-- No query yet: naming what is searchable is what teaches the feature. -->
      <div v-else-if="!hasSearched" class="flex flex-col gap-3">
        <p class="text-sm text-surface-600 dark:text-surface-300 m-0">
          Tapez au moins {{ MIN_QUERY_LENGTH }} caractères pour chercher dans&nbsp;:
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

      <p v-else-if="flatResults.length === 0" class="py-6 m-0 text-center text-surface-600 dark:text-surface-300">
        Aucun résultat pour «&nbsp;{{ query.trim() }}&nbsp;»
      </p>

      <!--
        The rows are divs rather than buttons on purpose. This is the ARIA combobox pattern: focus
        stays on the input and aria-activedescendant points at the current row, so the rows must not
        be focusable themselves. Elsewhere in the band space a clickable row should still be a button.
      -->
      <div
        v-else
        :id="LISTBOX_ID"
        ref="listboxRef"
        role="listbox"
        aria-label="Résultats de la recherche"
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
            :id="optionId(result)"
            :key="result.id"
            role="option"
            :aria-selected="result.id === activeResult?.id"
            :class="[
              'flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-all',
              result.id === activeResult?.id
                ? 'border-primary-200 dark:border-primary-700/50 bg-primary-50 dark:bg-primary-800/75 text-primary'
                : 'border-transparent'
            ]"
            @click="openResult(result)"
            @mousemove="setActiveResult(result)"
          >
            <span class="flex-1 min-w-0 truncate palette-title" v-html="highlightedTitle(result)" />
            <span v-if="result.subtitle" class="shrink-0 text-xs text-surface-600 dark:text-surface-300 truncate max-w-[10rem]">
              {{ result.subtitle }}
            </span>
          </div>
        </div>
      </div>
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
import { useDebounceFn } from '@vueuse/core'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, nextTick, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import bandSpaceSearchApi from '../../api/bandSpace/band-space-search.js'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import {
  flattenGroups,
  groupResultsByType,
  moveActiveIndex,
  routeForResult,
  SEARCH_TYPES
} from '../../utils/bandSpaceSearch.js'
import { highlightTerm } from '../../utils/highlight.js'

// A #header slot replaces the element PrimeVue points aria-labelledby at, which would leave the
// dialog with no accessible name at all, so the reference is redirected at the heading below.
const HEADING_ID = 'band-space-search-heading'
const LISTBOX_ID = 'band-space-search-listbox'
const KBD_CLASS =
  'font-sans p-1 min-w-5 inline-flex items-center justify-center rounded-md leading-none border border-surface-300 dark:border-surface-600 bg-surface-100 dark:bg-surface-800'
const MIN_QUERY_LENGTH = 2
const DEBOUNCE_MS = 250

const visible = defineModel('visible', { type: Boolean, default: false })

const router = useRouter()
const { currentSpaceId } = useBandSpaceNavigation()

const query = ref('')
const results = ref([])
const isSearching = ref(false)
const searchError = ref(null)
const hasSearched = ref(false)
const activeIndex = ref(0)
const listboxRef = ref(null)

// Monotonic, so a response that arrives after a newer keystroke is dropped instead of overwriting
// the newer results. Not reactive: nothing renders it.
let requestId = 0

const groups = computed(() => groupResultsByType(results.value))
const flatResults = computed(() => flattenGroups(groups.value))
const activeResult = computed(() => flatResults.value[activeIndex.value] ?? null)
const activeOptionId = computed(() =>
  activeResult.value ? optionId(activeResult.value) : undefined
)

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

function optionId(result) {
  return `band-space-search-option-${result.id}`
}

function highlightedTitle(result) {
  return highlightTerm(result.title, query.value.trim())
}

const runSearchDebounced = useDebounceFn(runSearch, DEBOUNCE_MS)

watch(query, (value) => {
  const trimmed = (value ?? '').trim()

  // Invalidate anything in flight straight away, so deleting back to one character cannot be
  // undone half a second later by a response for the longer term.
  requestId += 1
  activeIndex.value = 0

  if (trimmed.length < MIN_QUERY_LENGTH) {
    results.value = []
    searchError.value = null
    hasSearched.value = false
    isSearching.value = false
  } else {
    isSearching.value = true
  }

  // Fired even for a short term: the debounce keeps only the latest call, so this is what carries
  // the shortened term to runSearch, which then declines to ask the server for it.
  runSearchDebounced(trimmed)
})

// Watches the results too, not only the index: a new search leaves activeIndex at 0, so without
// this the list would keep the scroll position of the previous one and open below the active row.
watch([activeIndex, flatResults], async () => {
  await nextTick()
  listboxRef.value?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' })
})

async function runSearch(term) {
  if (term.length < MIN_QUERY_LENGTH || !currentSpaceId.value) {
    return
  }

  requestId += 1
  const id = requestId
  searchError.value = null

  try {
    const found = await bandSpaceSearchApi.search(currentSpaceId.value, term)
    if (id !== requestId) {
      return
    }
    results.value = found
  } catch (error) {
    if (id !== requestId) {
      return
    }
    searchError.value = error?.message ?? 'Erreur pendant la recherche'
    results.value = []
  } finally {
    if (id === requestId) {
      hasSearched.value = true
      isSearching.value = false
    }
  }
}

function moveActive(step) {
  activeIndex.value = moveActiveIndex(flatResults.value.length, activeIndex.value, step)
}

function setActiveResult(result) {
  const index = flatResults.value.indexOf(result)
  if (index !== -1) {
    activeIndex.value = index
  }
}

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

function reset() {
  query.value = ''
  results.value = []
  searchError.value = null
  hasSearched.value = false
  isSearching.value = false
  activeIndex.value = 0
  requestId += 1
}
</script>

<style scoped>
/* v-html content carries no scope attribute, so the highlight needs :deep to reach it. */
.palette-title :deep(mark) {
  background-color: rgba(245, 180, 0, 0.35);
  color: inherit;
  padding: 0 0.1em;
  border-radius: 2px;
}
</style>
