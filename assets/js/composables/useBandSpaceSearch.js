import { useDebounceFn } from '@vueuse/core'
import { computed, ref, toValue, watch } from 'vue'
import bandSpaceSearchApi from '../api/bandSpace/band-space-search.js'
import {
  flattenGroups,
  groupResultsByType,
  moveActiveIndex,
  searchOptionId
} from '../utils/bandSpaceSearch.js'

/**
 * Mirrors BandSpaceSearchProvider::MIN_QUERY_LENGTH. Below it the endpoint answers with an empty
 * collection rather than an error, so both callers can say so instead of searching for nothing.
 */
export const MIN_SEARCH_QUERY_LENGTH = 2

const DEBOUNCE_MS = 250

/**
 * One band space search, as the command palette and the chat attachment picker (#971) both need it:
 * a debounced call per typed term, the hits grouped by kind, and a single active row the arrow keys
 * move through.
 *
 * Extracted from CommandPalette when the picker became a second caller, rather than copied. The
 * `requestId` guard below is why: a copy that dropped it would look right and then let a slow
 * response for an abandoned term overwrite the results of a newer one.
 *
 * @param {import('vue').Ref<string|null>|(() => string|null)|string} bandSpaceId
 * @param {string} listboxId the caller's own list, since minting a DOM id is a component's business
 *   and not a search's. Keeping `useId()` out of here is also what makes this file unit testable:
 *   it needs a component instance, and there is none in a `node --test` process.
 */
export function useBandSpaceSearch(bandSpaceId, listboxId = '') {
  const query = ref('')
  const results = ref([])
  const isSearching = ref(false)
  const searchError = ref(null)
  const hasSearched = ref(false)
  const activeIndex = ref(0)

  // Monotonic, so a response that arrives after a newer keystroke is dropped instead of overwriting
  // the newer results. Not reactive: nothing renders it.
  let requestId = 0

  const groups = computed(() => groupResultsByType(results.value))
  const flatResults = computed(() => flattenGroups(groups.value))
  const activeResult = computed(() => flatResults.value[activeIndex.value] ?? null)
  const activeOptionId = computed(() =>
    activeResult.value ? searchOptionId(listboxId, activeResult.value) : undefined
  )

  const runSearchDebounced = useDebounceFn(runSearch, DEBOUNCE_MS)

  watch(query, (value) => {
    const trimmed = (value ?? '').trim()

    // Invalidate anything in flight straight away, so deleting back to one character cannot be
    // undone half a second later by a response for the longer term.
    requestId += 1
    activeIndex.value = 0

    if (trimmed.length < MIN_SEARCH_QUERY_LENGTH) {
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

  async function runSearch(term) {
    const spaceId = toValue(bandSpaceId)
    if (term.length < MIN_SEARCH_QUERY_LENGTH || !spaceId) {
      return
    }

    requestId += 1
    const id = requestId
    searchError.value = null

    try {
      const found = await bandSpaceSearchApi.search(spaceId, term)
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

  function reset() {
    query.value = ''
    results.value = []
    searchError.value = null
    hasSearched.value = false
    isSearching.value = false
    activeIndex.value = 0
    requestId += 1
  }

  return {
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
  }
}
