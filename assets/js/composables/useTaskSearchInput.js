import { useDebounceFn } from '@vueuse/core'
import { ref, watch } from 'vue'

const SEARCH_DEBOUNCE_MS = 300

/**
 * The text of a tasks search field: typed freely, sent to the board a moment after the last key so
 * each keystroke does not refetch, and following the filter when something else changes it.
 *
 * @param {() => string | null} query the filter's current search
 * @param {(text: string) => void} search sends the text to the board
 */
export function useTaskSearchInput(query, search) {
  const searchInput = ref(query() ?? '')

  watch(query, (current) => {
    if (current !== searchInput.value) searchInput.value = current ?? ''
  })

  const searchSoon = useDebounceFn(() => search(searchInput.value), SEARCH_DEBOUNCE_MS)

  return { searchInput, searchSoon }
}
