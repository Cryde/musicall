<template>
  <section
    v-if="musician || teacher || isOwner"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    aria-labelledby="profile-activities-title"
  >
    <h2 id="profile-activities-title" class="m-0 mb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.public.activities.title') }}
    </h2>
    <ul class="flex flex-col gap-2 list-none m-0 p-0">
      <li v-if="musician">
        <router-link
          :to="{ name: 'app_user_musician_profile', params: { username }, query: { from: 'profile' } }"
          class="flex items-center gap-3 p-2 rounded-lg bg-surface-50 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
        >
          <span class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center bg-sky-500/15 text-sky-600 dark:text-sky-400" aria-hidden="true">
            <i class="pi pi-headphones" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0">
              {{ musician.instrumentName || t('profile.public.activities.musician_fallback') }}
            </span>
            <span
              v-if="musician.statusLabel"
              class="block text-xs"
              :class="musician.isAvailable ? 'text-green-700 dark:text-green-400' : 'text-surface-500 dark:text-surface-400'"
            >
              {{ musician.statusLabel }}
            </span>
          </span>
          <i class="pi pi-chevron-right text-xs text-surface-400" aria-hidden="true" />
        </router-link>
      </li>
      <li v-else-if="isOwner">
        <Button
          :label="t('profile.public.create_musician_profile')"
          icon="pi pi-headphones"
          severity="secondary"
          outlined
          size="small"
          class="w-full"
          @click="emit('create-musician')"
        />
      </li>

      <li v-if="teacher">
        <router-link
          :to="{ name: 'app_user_teacher_profile', params: { username }, query: { from: 'profile' } }"
          class="flex items-center gap-3 p-2 rounded-lg bg-surface-50 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
        >
          <span class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center bg-amber-500/15 text-amber-600 dark:text-amber-400" aria-hidden="true">
            <i class="pi pi-graduation-cap" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0">{{ teacherTitle }}</span>
            <span v-if="teacherDetails" class="block text-xs text-surface-500 dark:text-surface-400">{{ teacherDetails }}</span>
          </span>
          <i class="pi pi-chevron-right text-xs text-surface-400" aria-hidden="true" />
        </router-link>
      </li>
      <li v-else-if="isOwner">
        <Button
          :label="t('profile.public.create_teacher_profile')"
          icon="pi pi-graduation-cap"
          severity="secondary"
          outlined
          size="small"
          class="w-full"
          @click="emit('create-teacher')"
        />
      </li>
    </ul>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { musicianActivity, teacherActivity } from '../../../utils/profileActivities.js'
import { formatLessonPrice } from '../../../utils/teacherPage.js'

/** « Ce que je fais » (#1317): one line per activity, each opening its page. */
const props = defineProps({
  profile: { type: Object, required: true },
  isOwner: { type: Boolean, default: false }
})

const emit = defineEmits(['create-musician', 'create-teacher'])
const { t, locale } = useI18n()

const username = computed(() => props.profile.username)
const musician = computed(() => musicianActivity(props.profile.musician))
const teacher = computed(() => teacherActivity(props.profile.teacher))

const teacherTitle = computed(() => {
  const names = teacher.value.instrumentNames.map((name) => name.toLocaleLowerCase(locale.value))
  if (names.length === 0) return t('profile.public.activities.teacher_fallback')
  const title = t('profile.public.activities.teacher', {
    instruments: new Intl.ListFormat(locale.value, { type: 'conjunction' }).format(names)
  })
  return title.charAt(0).toLocaleUpperCase(locale.value) + title.slice(1)
})

const teacherDetails = computed(() => {
  const { fromPrice, trial, trialPrice } = teacher.value
  const parts = []
  if (fromPrice !== null)
    parts.push(t('profile.public.activities.from_price', { price: formatLessonPrice(fromPrice) }))
  if (trial === 'free') parts.push(t('profile.public.activities.trial_free'))
  if (trial === 'paid')
    parts.push(t('profile.public.activities.trial_paid', { price: formatLessonPrice(trialPrice) }))
  return parts.join(', ')
})
</script>
