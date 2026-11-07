<template>
  <ul class="flex flex-col gap-3 list-none m-0 p-0">
    <li v-for="course in courses" :key="course.id">
      <router-link :to="{ name: 'app_course_show', params: { slug: course.slug } }" class="flex items-center gap-3 group">
        <img v-if="course.cover" :src="course.cover" alt="" class="w-24 h-16 rounded-lg object-cover shrink-0" />
        <span v-else class="w-24 h-16 rounded-lg shrink-0 bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
          <i class="pi pi-book text-surface-400" aria-hidden="true" />
        </span>
        <span class="min-w-0">
          <span class="block font-semibold text-surface-900 dark:text-surface-0 group-hover:text-primary line-clamp-2">{{ course.title }}</span>
          <span class="block text-sm text-surface-500 dark:text-surface-400">
            {{ [course.sub_category?.title, formatDate(course.publication_datetime)].filter(Boolean).join(', ') }}
          </span>
        </span>
      </router-link>
    </li>
  </ul>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

/** A member's published courses, as the teacher page and the profile list them. */
defineProps({
  courses: { type: Array, required: true }
})

const { locale } = useI18n()

function formatDate(value) {
  if (!value) return ''
  return new Date(value).toLocaleDateString(locale.value, {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}
</script>
