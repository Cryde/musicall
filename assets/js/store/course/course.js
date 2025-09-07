import {defineStore} from 'pinia'
import {computed, readonly, ref} from 'vue';
import courseApi from '../../api/course/course.js';

export const useCoursesStore = defineStore('courses', () => {

  const courses = ref([]);
  const courseCategories = ref([]);

  async function loadCourses({page = 1, slug = null, orientation = 'desc'}) {
    const coursesResponse = await courseApi.getCourses({page, slug, orientation});

    courses.value = coursesResponse.member;
  }

  async function loadCategories() {
    courseCategories.value = await courseApi.getCourseCategories();
  }

  function clear() {
    courses.value = [];
    courseCategories.value = [];
  }

  return {
    loadCourses,
    loadCategories,
    clear,
    courses: readonly(courses),
    courseCategories: readonly(courseCategories)
  }
});
