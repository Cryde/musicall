<template>
  <article
    class="flex flex-col bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 sm:p-5 transition-shadow hover:shadow-md"
  >
    <div class="flex gap-4 items-start">
      <Avatar
        :image="teacher.profile_picture_url ?? undefined"
        :label="teacher.profile_picture_url ? undefined : initials"
        :style="teacher.profile_picture_url ? undefined : getAvatarStyle(teacher.username)"
        shape="circle"
        class="!w-16 !h-16 sm:!w-24 sm:!h-24 !text-2xl sm:!text-4xl font-semibold shrink-0"
        aria-hidden="true"
      />

      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0 truncate">
            <router-link :to="teacherRoute" class="hover:underline focus-visible:underline">
              {{ teacher.name }}
            </router-link>
          </h3>
          <span
            v-if="placeLabel"
            class="shrink-0 inline-flex items-center gap-1 text-sm text-surface-600 dark:text-surface-400"
          >
            <i class="pi pi-map-marker text-xs" aria-hidden="true" />
            {{ placeLabel }}
          </span>
        </div>

        <p class="text-sm text-surface-600 dark:text-surface-400 first-letter:uppercase">
          {{ teachesLine }}
        </p>

        <div v-if="teacher.lesson_price != null || teacher.free_trial" class="flex flex-wrap items-baseline gap-x-2 gap-y-1 mt-2 mb-1.5">
          <template v-if="teacher.lesson_price != null">
            <span class="text-lg font-bold text-surface-900 dark:text-surface-0">
              {{ t('search.teacher.price_from', { price: formatLessonPrice(teacher.lesson_price) }) }}
            </span>
            <span class="text-sm text-surface-600 dark:text-surface-400">
              {{ t('search.teacher.per_minutes', { minutes: teacher.lesson_minutes }) }}
            </span>
          </template>
          <span
            v-if="teacher.free_trial"
            class="px-2 py-0.5 rounded-md text-sm font-medium bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300"
          >
            {{ t('search.teacher.free_trial_badge') }}
          </span>
        </div>

        <ul v-if="teacher.student_levels.length" class="flex flex-wrap gap-1.5 mt-2" :aria-label="t('search.teacher.level')">
          <li
            v-for="level in teacher.student_levels"
            :key="level"
            class="px-2.5 py-1 rounded-md text-sm font-medium bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200"
          >
            {{ getStudentLevelLabel(level) }}
          </li>
        </ul>

        <p v-if="teacher.styles.length" class="mt-1.5 text-xs text-surface-600 dark:text-surface-400">
          {{ teacher.styles.map((style) => style.name).join(', ') }}
        </p>
      </div>
    </div>

    <div class="flex items-center gap-2 mt-auto pt-4">
      <Button
        v-if="teacher.media"
        :label="isPlaying ? t('search.teacher.listen_close') : t('search.teacher.listen')"
        :icon="isPlaying ? 'pi pi-times' : 'pi pi-play'"
        :aria-label="isPlaying ? undefined : t('search.teacher.listen_aria', { name: teacher.name })"
        :aria-expanded="isPlaying"
        severity="secondary"
        size="small"
        @click="emit('toggle-listen')"
      />
      <router-link
        :to="teacherRoute"
        :aria-label="t('search.teacher.view_aria', { name: teacher.name })"
        class="ml-auto inline-flex items-center px-3 py-1.5 rounded-lg border border-surface-300 dark:border-surface-600 text-sm font-medium text-surface-800 dark:text-surface-100 hover:bg-surface-50 dark:hover:bg-surface-800"
      >
        {{ t('search.teacher.view') }}
      </router-link>
    </div>

    <div
      v-if="isPlaying && teacher.media"
      class="mt-3 overflow-hidden rounded-xl"
      :style="{ height: `${mediaEmbedHeight(teacher.media)}px` }"
    >
      <MediaEmbedFrame :media="teacher.media" autoplay compact />
    </div>
  </article>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MediaEmbedFrame from '../../components/Media/MediaEmbedFrame.vue'
import { getStudentLevelLabel } from '../../constants/teacherProfile.js'
import { intlLocale } from '../../i18n/formatLocale.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { mediaEmbedHeight } from '../../utils/mediaEmbed.js'
import { formatLessonPrice } from '../../utils/teacherPage.js'
import { formatDistance, instrumentsOf } from '../../utils/teacherSearch.js'

/** One teacher of the teacher search (#1314). « Écouter » plays their first media in the card, one card at a time. */
const props = defineProps({
  teacher: { type: Object, required: true },
  isPlaying: { type: Boolean, default: false }
})

const emit = defineEmits(['toggle-listen'])

const { t } = useI18n()

const initials = computed(() =>
  props.teacher.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word.charAt(0).toLocaleUpperCase())
    .join('')
)

const teachesLine = computed(() => {
  const names = props.teacher.instruments.map((instrument) => instrument.name)
  return names.length
    ? t('search.teacher.teaches', { instruments: instrumentsOf(names, intlLocale()) })
    : t('search.teacher.teaches_music')
})

const placeLabel = computed(() => {
  if (props.teacher.distance != null) return formatDistance(props.teacher.distance, intlLocale())
  return props.teacher.online ? t('search.teacher.online_place') : null
})

const teacherRoute = computed(() => {
  // The searched instrument comes first: the teacher page opens on it.
  const slug = props.teacher.instruments[0]?.slug
  return {
    name: 'app_user_teacher_profile',
    params: { username: props.teacher.username },
    query: { ...(slug ? { instrument: slug } : {}), from: 'search' }
  }
})
</script>
