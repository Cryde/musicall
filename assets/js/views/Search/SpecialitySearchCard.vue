<template>
  <article
    class="flex flex-col bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 p-4 sm:p-5 transition-shadow hover:shadow-md"
  >
    <div class="flex gap-4 items-start">
      <Avatar
        :image="result.profile_picture_url ?? undefined"
        :label="result.profile_picture_url ? undefined : result.name.charAt(0).toUpperCase()"
        :style="result.profile_picture_url ? undefined : getAvatarStyle(result.username)"
        shape="circle"
        class="!w-16 !h-16 sm:!w-20 sm:!h-20 !text-2xl sm:!text-3xl font-semibold shrink-0"
        aria-hidden="true"
      />

      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0 truncate">
            <router-link
              :to="{ name: pageRoute, params: { username: result.username }, query: { from: 'search' } }"
              class="hover:underline focus-visible:underline"
            >
              {{ result.name }}
            </router-link>
          </h3>
          <span v-if="placeLabel" class="shrink-0 inline-flex items-center gap-1 text-sm text-surface-600 dark:text-surface-400">
            <i class="pi pi-map-marker text-xs" aria-hidden="true" />
            {{ placeLabel }}
          </span>
        </div>

        <p v-if="result.headline" class="text-sm text-surface-600 dark:text-surface-400 line-clamp-2">{{ result.headline }}</p>

        <div v-if="result.price != null || days" class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mt-2">
          <template v-if="result.price != null">
            <span class="text-lg font-bold text-surface-900 dark:text-surface-0">
              {{ t('search.service.price_from', { price: formatLessonPrice(result.price) }) }}
            </span>
            <span class="text-sm text-surface-600 dark:text-surface-400">{{ t(unitKeys[result.price_unit]) }}</span>
          </template>
          <span v-if="days" class="text-sm text-surface-600 dark:text-surface-400">
            {{ t('search.service.days', days) }}
          </span>
        </div>

        <ul v-if="result.specialities.length" class="flex flex-wrap gap-1.5 mt-2" :aria-label="t('search.service.speciality')">
          <li
            v-for="speciality in result.specialities"
            :key="speciality"
            class="px-2.5 py-1 rounded-md text-sm font-medium bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200"
          >
            {{ t(specialityKeys[speciality]) }}
          </li>
        </ul>
        <p v-if="result.styles.length" class="mt-2 text-sm text-surface-600 dark:text-surface-400">
          {{ result.styles.map((style) => style.name).join(', ') }}
        </p>
      </div>
    </div>
  </article>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { intlLocale } from '../../i18n/formatLocale.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { formatLessonPrice } from '../../utils/teacherPage.js'
import { formatDistance } from '../../utils/teacherSearch.js'

/** One member of a search by speciality (#1410, #1411). */
const props = defineProps({
  /** One result, straight from the API. */
  result: { type: Object, required: true },
  /** The route of the kind's page. */
  pageRoute: { type: String, required: true },
  specialityKeys: { type: Object, required: true },
  /** Each unit's words after a price, « le titre ». */
  unitKeys: { type: Object, required: true },
  /** The turnaround or the delivery time the kind names, in days. */
  days: { type: Number, default: null }
})

const { t } = useI18n()

// Remote only for a kind that works that way: the others' cards never say `online`.
const placeLabel = computed(() => {
  if (props.result.distance != null) return formatDistance(props.result.distance, intlLocale())
  return props.result.online ? t('search.service.remote_place') : null
})
</script>
