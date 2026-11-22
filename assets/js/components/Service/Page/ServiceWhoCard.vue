<template>
  <!-- « Qui est Julien ? »: the member behind the service -->
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 flex flex-col gap-3">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0">{{ t('profile.service_page.who', { name: page.display_name }) }}</h2>
    <div class="flex items-center gap-3">
      <MemberAvatar :page="page" size="w-11! h-11!" />
      <div class="min-w-0">
        <p class="font-semibold text-surface-900 dark:text-surface-0">{{ page.display_name }}</p>
        <p class="text-sm text-surface-500 dark:text-surface-400">{{ memberLine }}</p>
      </div>
    </div>
    <p v-if="page.bio" class="text-sm text-surface-700 dark:text-surface-300 whitespace-pre-line">{{ page.bio }}</p>
    <Button
      as="router-link"
      :to="{ name: 'app_user_public_profile', params: { username: page.username } }"
      :label="t('profile.common.view_profile')"
      severity="secondary"
      outlined
      class="w-full"
    />
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MemberAvatar from './MemberAvatar.vue'

/** « Qui est Julien ? » on a service page (#1410): the member, their first instrument and their bio. */
const props = defineProps({
  /** The page: `display_name`, `username`, `bio`, `member_since`, `musician_instruments`, `profile_picture_url`. */
  page: { type: Object, required: true }
})

const { t, locale } = useI18n()

/** « Guitariste, membre depuis 2014 ». */
const memberLine = computed(() => {
  const since = t('profile.teacher_page.member_since', {
    year: new Date(props.page.member_since).getFullYear()
  })
  const instrument = props.page.musician_instruments?.[0]?.instrument_name
  return instrument
    ? `${instrument}, ${since.charAt(0).toLocaleLowerCase(locale.value)}${since.slice(1)}`
    : since
})
</script>
