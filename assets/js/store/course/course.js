import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import courseApi from '../../api/course/course.js'

export const useCoursesStore = defineStore('courses', () => {
  const courses = ref([])
  const courseCategories = ref([])

  async function loadCourses({ page = 1, slug = null, orientation = 'desc' }) {
    const { member } = await courseApi.getCourses({ page, slug, orientation })

    courses.value = [...courses.value, ...member]
    return member
  }

  async function loadCategories() {
    courseCategories.value = await courseApi.getCourseCategories()
  }

  function clear() {
    courses.value = []
    courseCategories.value = []
  }

  function resetCourses() {
    courses.value = []
  }

  return {
    loadCourses,
    resetCourses,
    loadCategories,
    clear,
    courses: readonly(courses),
    courseCategories: readonly(courseCategories)
  }
})
