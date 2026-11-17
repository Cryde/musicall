<template>
  <article
    class="flex flex-col bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 sm:p-5 transition-shadow hover:shadow-md"
  >
    <div class="flex gap-4 items-start">
      <Avatar
        :image="engineer.profile_picture_url ?? undefined"
        :label="engineer.profile_picture_url ? undefined : engineer.name.charAt(0).toUpperCase()"
        :style="engineer.profile_picture_url ? undefined : getAvatarStyle(engineer.username)"
        shape="circle"
        class="!w-16 !h-16 sm:!w-20 sm:!h-20 !text-2xl sm:!text-3xl font-semibold shrink-0"
        aria-hidden="true"
      />

      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0 truncate">
            <router-link
              :to="{ name: 'app_user_sound_engineer', params: { username: engineer.username }, query: { from: 'search' } }"
              class="hover:underline focus-visible:underline"
            >
              {{ engineer.name }}
            </router-link>
          </h3>
          <span v-if="placeLabel" class="shrink-0 inline-flex items-center gap-1 text-sm text-surface-600 dark:text-surface-400">
            <i class="pi pi-map-marker text-xs" aria-hidden="true" />
            {{ placeLabel }}
          </span>
        </div>

        <p v-if="engineer.headline" class="text-sm text-surface-600 dark:text-surface-400 line-clamp-2">{{ engineer.headline }}</p>

        <div v-if="engineer.price != null || engineer.turnaround_days" class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mt-2">
          <template v-if="engineer.price != null">
            <span class="text-lg font-bold text-surface-900 dark:text-surface-0">
              {{ t('search.sound_engineer.price_from', { price: formatLessonPrice(engineer.price) }) }}
            </span>
            <span class="text-sm text-surface-600 dark:text-surface-400">{{ t(UNIT_KEYS[engineer.price_unit]) }}</span>
          </template>
          <span v-if="engineer.turnaround_days" class="text-sm text-surface-600 dark:text-surface-400">
            {{ t('search.sound_engineer.turnaround', engineer.turnaround_days) }}
          </span>
        </div>

        <ul v-if="engineer.specialities.length" class="flex flex-wrap gap-1.5 mt-2" :aria-label="t('search.sound_engineer.speciality')">
          <li
            v-for="speciality in engineer.specialities"
            :key="speciality"
            class="px-2.5 py-1 rounded-md text-sm font-medium bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200"
          >
            {{ t(SPECIALITY_KEYS[speciality]) }}
          </li>
        </ul>
        <p v-if="engineer.styles.length" class="mt-2 text-sm text-surface-600 dark:text-surface-400">
          {{ engineer.styles.map((style) => style.name).join(', ') }}
        </p>
      </div>
    </div>
  </article>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { SPECIALITY_KEYS, UNIT_KEYS } from '../../constants/soundEngineering.js'
import { intlLocale } from '../../i18n/formatLocale.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { formatLessonPrice } from '../../utils/teacherPage.js'
import { formatDistance } from '../../utils/teacherSearch.js'

/** One sound engineer of the search (#1410). */
const props = defineProps({
  /** One SoundEngineerSearchResult, straight from the API. */
  engineer: { type: Object, required: true }
})

const { t } = useI18n()

const placeLabel = computed(() => {
  if (props.engineer.distance != null) return formatDistance(props.engineer.distance, intlLocale())
  return props.engineer.online ? t('search.sound_engineer.remote_place') : null
})
</script>
