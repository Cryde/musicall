import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import teacherSearchApi from '../../api/search/teacher.js'
import { teacherSearchParams } from '../../utils/teacherSearch.js'

/** The teacher search's results (#1314), kept while a visitor opens a teacher and comes back. */
export const useTeacherSearchStore = defineStore('teacherSearch', () => {
  const teachers = ref([])
  const totalItems = ref(0)
  const currentPage = ref(1)
  // What the results were searched with: the lessons page names them and pages on through them (#1315).
  const filters = ref(null)
  const instruments = ref([])
  // The filters search as they change, so two answers can cross: only the latest one shows, and the
  // caller learns whether its own did.
  let latestRequest = 0

  /** `searchFilters` as teacherSearchParams() reads them; a page past the first is added to the list on screen. */
  async function search(searchFilters, page = 1) {
    const request = ++latestRequest
    // Copied before asking: the visitor may change the filters while the answer is on its way.
    const searched = { ...searchFilters }
    const data = await teacherSearchApi.searchTeachers(teacherSearchParams(searched, page))
    if (request !== latestRequest) return false

    teachers.value = page > 1 ? [...teachers.value, ...data.member] : data.member
    totalItems.value = data.totalItems
    currentPage.value = page
    // A next page was searched with the same filters: the cards keep their links.
    if (page === 1) filters.value = searched
    return true
  }

  async function loadInstruments() {
    if (instruments.value.length === 0) {
      instruments.value = await teacherSearchApi.getTaughtInstruments()
    }
  }

  const hasMore = computed(() => teachers.value.length < totalItems.value)

  return {
    search,
    loadInstruments,
    teachers: readonly(teachers),
    totalItems: readonly(totalItems),
    currentPage: readonly(currentPage),
    filters: readonly(filters),
    instruments: readonly(instruments),
    hasMore
  }
})
