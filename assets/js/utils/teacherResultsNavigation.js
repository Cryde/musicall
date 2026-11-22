/**
 * Moving through the teacher search's results from a lessons page (#1315). Takes the search store, the router and
 * the route, so the tests drive them with fakes.
 */
import { searchResultsPosition, teacherPageLocation } from './teacherResults.js'
import { teacherSearchUrlQuery } from './teacherSearch.js'

const TEACHER_SEARCH_ROUTE = 'app_search_teacher'

/** The page after the last one loaded, with the filters the list was searched with. */
export function loadNextResultsPage(store) {
  if (!store.filters) return Promise.resolve(false)
  return store.search(store.filters, store.currentPage + 1)
}

// Replaced, not pushed: « Résultats » stays one step back.
function openTeacher(store, router, teacher) {
  router.replace(teacherPageLocation(teacher.username, store.filters?.instrument?.slug))
}

export function goToPreviousTeacher({ store, router, route }) {
  const previous = searchResultsPosition(store, route.params.username)?.previous
  if (previous) openTeacher(store, router, previous)
}

/**
 * The next teacher loaded, or the first of the next page once it is in; nothing after the last result. A press
 * while that page loads is ignored, and the route is read again after it, in case the visitor moved meanwhile.
 *
 * @param {{value: boolean}} isLoading a ref the results bar shows as the button's spinner
 */
export async function goToNextTeacher({ store, router, route, isLoading }) {
  if (isLoading.value) return
  const position = searchResultsPosition(store, route.params.username)
  if (!position) return
  if (!position.next && position.hasMore) {
    isLoading.value = true
    try {
      await loadNextResultsPage(store)
    } catch {
      // Stays on this teacher: another press tries the page again.
    } finally {
      isLoading.value = false
    }
  }
  const next = searchResultsPosition(store, route.params.username)?.next
  if (next) openTeacher(store, router, next)
}

/** A step back when the page before is the kept-alive search, its scroll included; a new visit to it otherwise. */
export function goBackToResults({ store, router, historyState }) {
  const back = historyState?.back
  if (typeof back === 'string' && router.resolve(back).name === TEACHER_SEARCH_ROUTE) {
    router.back()
    return
  }
  router.push({ name: TEACHER_SEARCH_ROUTE, query: teacherSearchUrlQuery(store.filters) })
}
