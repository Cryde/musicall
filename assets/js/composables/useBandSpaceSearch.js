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

/** Mirrors BandSpaceRecentsOrder: how the list shown before anything is typed is sorted (#1047). */
export const RECENTS_ORDER = Object.freeze({ UPDATED: 'updated', OPENED: 'opened' })

/**
 * One remembered order per member, shared by the palette and the chat attachment picker: two people
 * signing in on the same browser each keep their own. No username, no key, nothing remembered.
 *
 * @param {?string} username
 */
export function recentsOrderStorageKey(username) {
  return username ? `band_space_recents_order_${username}` : ''
}

/** localStorage, made safe: a private window or blocked site data throws, and then nothing is remembered. */
function browserStorage() {
  return {
    get(key) {
      try {
        return globalThis.localStorage?.getItem(key) ?? null
      } catch {
        return null
      }
    },
    set(key, value) {
      try {
        globalThis.localStorage?.setItem(key, value)
      } catch {
        // Not remembered, which only means choosing the order again next time.
      }
    }
  }
}

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
 * @param {{ orderStorageKey?: import('vue').MaybeRefOrGetter<string>, storage?: { get: Function, set: Function } }} options
 *   where the member's choice of order is remembered (#1047), per member, so it is set once rather
 *   than on every open. No key, nothing remembered.
 */
export function useBandSpaceSearch(
  bandSpaceId,
  listboxId = '',
  { orderStorageKey = '', storage = browserStorage() } = {}
) {
  const query = ref('')
  const results = ref([])
  const isSearching = ref(false)
  const searchError = ref(null)
  const hasSearched = ref(false)
  const activeIndex = ref(0)
  // One kind or all of them (#1046), sent with every call, the recents included.
  const selectedType = ref(null)
  // What the panel shows before anything is typed: the space's latest items, newest first, flat.
  const recents = ref([])
  const isLoadingRecents = ref(false)
  const order = ref(readStoredOrder())

  // Monotonic, so a response that arrives after a newer keystroke is dropped instead of overwriting
  // the newer results. Not reactive: nothing renders it.
  let requestId = 0

  const groups = computed(() => groupResultsByType(results.value))
  const isQueryShort = computed(() => (query.value ?? '').trim().length < MIN_SEARCH_QUERY_LENGTH)
  // The rows the arrow keys move through: the recents in date order while nothing is typed, the hits
  // in group order once something is. One list either way, so one active row and one listbox.
  const flatResults = computed(() =>
    isQueryShort.value ? recents.value : flattenGroups(groups.value)
  )
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
      // Back to an empty box: the recents are what it shows, and the guard above has just dropped any
      // request for them that was still in flight.
      loadRecents()
    } else {
      isSearching.value = true
      // A recents request this has just superseded will never clear its own flag.
      isLoadingRecents.value = false
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
      const found = await bandSpaceSearchApi.search(spaceId, term, selectedType.value)
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

  /**
   * The recents go through the same `requestId` guard as a search, which is the whole point of it
   * living here: the member can start typing before they answer, and a late answer must not land on
   * top of the first results.
   */
  async function loadRecents() {
    const spaceId = toValue(bandSpaceId)
    if (!spaceId) {
      return
    }

    requestId += 1
    const id = requestId
    isLoadingRecents.value = true

    try {
      const found = await bandSpaceSearchApi.search(spaceId, '', selectedType.value, order.value)
      if (id === requestId) {
        recents.value = found
      }
    } catch (error) {
      // Recents are a convenience: a failure leaves the hint on screen rather than an error.
      console.error('Failed to load the recent band space items:', error)
      if (id === requestId) {
        recents.value = []
      }
    } finally {
      if (id === requestId) {
        isLoadingRecents.value = false
      }
    }
  }

  /** One kind at a time: picking the selected one again goes back to every kind. */
  function toggleType(type) {
    selectedType.value = selectedType.value === type ? null : type
    activeIndex.value = 0

    const trimmed = (query.value ?? '').trim()
    if (trimmed.length < MIN_SEARCH_QUERY_LENGTH) {
      loadRecents()

      return
    }

    isSearching.value = true
    runSearch(trimmed)
  }

  /** Between the band's latest changes and what the member opened last. Only the recents depend on it. */
  function toggleOrder() {
    order.value =
      order.value === RECENTS_ORDER.OPENED ? RECENTS_ORDER.UPDATED : RECENTS_ORDER.OPENED
    activeIndex.value = 0

    const key = toValue(orderStorageKey)
    if (key) {
      storage.set(key, order.value)
    }

    if ((query.value ?? '').trim().length < MIN_SEARCH_QUERY_LENGTH) {
      loadRecents()
    }
  }

  function readStoredOrder() {
    const key = toValue(orderStorageKey)

    return key && storage.get(key) === RECENTS_ORDER.OPENED
      ? RECENTS_ORDER.OPENED
      : RECENTS_ORDER.UPDATED
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

  /**
   * Called by both dialogs when they open, which is when the recents are wanted. The order is read
   * back rather than reset: it is the member's standing choice, not part of one search.
   */
  function reset() {
    query.value = ''
    results.value = []
    searchError.value = null
    hasSearched.value = false
    isSearching.value = false
    activeIndex.value = 0
    selectedType.value = null
    order.value = readStoredOrder()
    recents.value = []
    requestId += 1
    loadRecents()
  }

  return {
    activeOptionId,
    activeResult,
    flatResults,
    groups,
    hasSearched,
    isLoadingRecents,
    isSearching,
    moveActive,
    order,
    query,
    recents,
    reset,
    searchError,
    selectedType,
    setActiveResult,
    toggleOrder,
    toggleType
  }
}
