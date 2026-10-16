<template>
  <section
    class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-6 lg:px-10 lg:py-8"
    aria-labelledby="home-teachers-title"
  >
    <div class="flex flex-col sm:flex-row sm:items-center gap-5">
      <ul v-if="avatarTeachers.length > 0" class="m-0 p-0 list-none flex shrink-0" :aria-label="t('home.teachers.avatars_aria_label')">
        <li v-for="(teacher, index) in avatarTeachers" :key="teacher.username" :class="{ '-ml-3': index > 0 }">
          <router-link
            :to="{ name: 'app_user_teacher_profile', params: { username: teacher.username } }"
            class="block rounded-full ring-4 ring-surface-0 dark:ring-surface-900"
            :aria-label="t('home.teachers.view_profile', { username: teacher.username })"
          >
            <Avatar
              v-if="teacher.profile_picture_url"
              :image="teacher.profile_picture_url"
              :pt="{ image: { alt: '' } }"
              shape="circle"
              size="large"
            />
            <Avatar
              v-else
              :label="teacher.username.charAt(0).toUpperCase()"
              :style="getAvatarStyle(teacher.username)"
              shape="circle"
              size="large"
            />
          </router-link>
        </li>
      </ul>
      <div class="flex flex-col gap-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 id="home-teachers-title" class="m-0 text-xl font-bold text-surface-900 dark:text-surface-0">
            {{ t('home.teachers.title') }}
          </h2>
          <span class="rounded-full bg-primary-100 dark:bg-primary-400/15 px-2.5 py-0.5 text-xs font-bold text-primary-800 dark:text-primary-300">
            {{ t('home.teachers.soon') }}
          </span>
        </div>
        <p class="m-0 text-surface-700 dark:text-surface-300">
          {{ t('home.teachers.intro') }}
        </p>
      </div>
    </div>
    <div class="flex flex-wrap gap-3 shrink-0">
      <Button as="router-link" :to="{ name: 'app_search_teacher' }" :label="t('home.teachers.see_all')" severity="secondary" outlined />
      <Button :label="t('home.teachers.i_am_teacher')" icon="pi pi-graduation-cap" @click="$emit('create-teacher-profile')" />
    </div>
  </section>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAvatarStyle } from '../../utils/avatar.js'

/** The teachers, ahead of their search (#1074): who is already here, and a way in for a teacher. */
const props = defineProps({
  teachers: { type: Array, default: () => [] }
})

defineEmits(['create-teacher-profile'])

const { t } = useI18n()

const MAX_AVATARS = 5
const avatarTeachers = computed(() => props.teachers.slice(0, MAX_AVATARS))
</script>
