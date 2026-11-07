<template>
  <section
    v-if="courses.length"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    aria-labelledby="profile-courses-title"
  >
    <h2 id="profile-courses-title" class="m-0 mb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.teacher_page.courses_title') }}
    </h2>
    <MemberCourseList :courses="courses" />
  </section>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import courseApi from '../../../api/course/course.js'
import MemberCourseList from '../../Course/MemberCourseList.vue'

/** « Ses cours sur MusicAll » (#1317): left out when there are none, or when they fail to load. */
const props = defineProps({
  username: { type: String, required: true }
})

const COURSES_SHOWN = 3

const { t } = useI18n()
const courses = ref([])

async function load() {
  const username = props.username
  try {
    const data = await courseApi.getMemberCourses(username)
    // The visitor may have moved on to another member while this was loading.
    if (username !== props.username) return
    courses.value = (data.member ?? []).slice(0, COURSES_SHOWN)
  } catch {
    courses.value = []
  }
}

watch(() => props.username, load, { immediate: true })
</script>
