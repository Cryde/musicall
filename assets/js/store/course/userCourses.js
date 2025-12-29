import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import userCourseApi from '../../api/user/course.js'

export const useUserCoursesStore = defineStore('userCourses', () => {
  const courses = ref([])
  const isLoading = ref(false)
  const totalItems = ref(0)
  const currentPage = ref(1)
  const itemsPerPage = ref(10)
  const filters = ref({
    status: null,
    category: null,
    sortBy: 'creation_datetime',
    sortOrder: 'desc'
  })

  async function loadCourses() {
    isLoading.value = true
    try {
      const response = await userCourseApi.getCourses({
        page: currentPage.value,
        itemsPerPage: itemsPerPage.value,
        status: filters.value.status,
        category: filters.value.category,
        sortBy: filters.value.sortBy,
        sortOrder: filters.value.sortOrder
      })
      courses.value = response.member || []
      totalItems.value = response.totalItems ?? 0
    } catch (e) {
      console.error('Failed to load user courses:', e)
      courses.value = []
      totalItems.value = 0
    } finally {
      isLoading.value = false
    }
  }

  async function deleteCourse(id) {
    isLoading.value = true
    try {
      await userCourseApi.delete(id)
      await loadCourses()
      return true
    } catch (e) {
      console.error('Failed to delete course:', e)
      return false
    } finally {
      isLoading.value = false
    }
  }

  function setPage(page) {
    currentPage.value = page
  }

  function setItemsPerPage(value) {
    itemsPerPage.value = value
    currentPage.value = 1
  }

  function setFilters(newFilters) {
    filters.value = { ...filters.value, ...newFilters }
    currentPage.value = 1
  }

  function resetFilters() {
    filters.value = {
      status: null,
      category: null,
      sortBy: 'creation_datetime',
      sortOrder: 'desc'
    }
    currentPage.value = 1
  }

  function clear() {
    courses.value = []
    isLoading.value = false
    totalItems.value = 0
    currentPage.value = 1
    itemsPerPage.value = 10
    resetFilters()
  }

  return {
    courses: readonly(courses),
    isLoading: readonly(isLoading),
    totalItems: readonly(totalItems),
    currentPage: readonly(currentPage),
    itemsPerPage: readonly(itemsPerPage),
    filters: readonly(filters),
    loadCourses,
    deleteCourse,
    setPage,
    setItemsPerPage,
    setFilters,
    resetFilters,
    clear
  }
})
