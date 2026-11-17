import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import teacherSearchApi from '../../api/search/teacher.js'

/** The teacher search's results (#1314), kept while a visitor opens a teacher and comes back. */
export const useTeacherSearchStore = defineStore('teacherSearch', () => {
  const teachers = ref([])
  const totalItems = ref(0)
  const currentPage = ref(1)
  const instruments = ref([])
  // The filters search as they change, so two answers can cross: only the latest one shows, and the
  // caller learns whether its own did.
  let latestRequest = 0

  /** `params` from teacherSearchParams(); a page past the first is added to the list on screen. */
  async function search(params) {
    const request = ++latestRequest
    const data = await teacherSearchApi.searchTeachers(params)
    if (request !== latestRequest) return false

    const page = params.page ?? 1
    teachers.value = page > 1 ? [...teachers.value, ...data.member] : data.member
    totalItems.value = data.totalItems
    currentPage.value = page
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
    instruments: readonly(instruments),
    hasMore
  }
})
