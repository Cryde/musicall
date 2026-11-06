<template>
  <article class="flex gap-3 p-4">
    <span
      class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center bg-primary-50 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300"
      aria-hidden="true"
    >
      <i :class="icon" />
    </span>
    <div class="flex-1 min-w-0 flex flex-col gap-2">
      <p class="text-sm text-surface-600 dark:text-surface-300 m-0">
        <span class="font-semibold text-surface-900 dark:text-surface-0">{{ displayName }}</span>
        {{ t(activitySentenceKey(item)) }} ·
        <time :datetime="item.date">{{ formatDateCompactWithYear(item.date) }}</time>
      </p>

      <div
        v-if="item.type === 'announce'"
        class="flex flex-wrap items-center gap-x-3 gap-y-2 p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
      >
        <Tag
          :value="item.announce.type === TYPES_ANNOUNCE_BAND ? t('profile.announce_item.looking_for_band') : t('profile.announce_item.looking_for_musician')"
          :severity="item.announce.type === TYPES_ANNOUNCE_BAND ? 'success' : 'info'"
          size="small"
        />
        <span class="font-semibold text-surface-900 dark:text-surface-0">{{ item.announce.instrument_name }}</span>
        <span class="text-sm text-surface-500 dark:text-surface-400">
          {{ [...item.announce.styles, item.announce.location_name].filter(Boolean).join(', ') }}
        </span>
        <Button
          :label="t('profile.wall.activity.see_announce')"
          size="small"
          outlined
          class="sm:ml-auto"
          @click="showAnnounces"
        />
      </div>

      <RouterLink
        v-else-if="item.publication"
        :to="{
          name: item.type === 'course' ? 'app_course_show' : 'app_publication_show',
          params: { slug: item.publication.slug }
        }"
        class="flex items-center gap-3 p-2 rounded-lg bg-surface-50 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700"
      >
        <img
          v-if="item.publication.cover"
          :src="item.publication.cover"
          alt=""
          class="w-16 h-16 rounded-md object-cover shrink-0"
          loading="lazy"
        />
        <span class="min-w-0">
          <span class="block font-semibold text-surface-900 dark:text-surface-0 truncate">{{ item.publication.title }}</span>
          <span class="block text-sm text-surface-500 dark:text-surface-400">
            {{ item.type === 'course' ? t('profile.wall.activity.course_label') : t('profile.wall.activity.publication_label') }},
            {{ item.publication.sub_category.title }}
          </span>
        </span>
      </RouterLink>

      <template v-else-if="item.media">
        <button
          type="button"
          class="flex items-center gap-3 p-2 rounded-lg bg-surface-50 dark:bg-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 text-left cursor-pointer"
          :aria-label="t('profile.wall.play', { title: item.media.title || item.media.url })"
          @click="isPlaying = true"
        >
          <span class="relative w-24 aspect-video shrink-0 rounded-md overflow-hidden bg-surface-900">
            <img v-if="thumbnailUrl" :src="thumbnailUrl" alt="" class="w-full h-full object-cover" loading="lazy" />
            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
              <i class="pi pi-play text-white" />
            </span>
          </span>
          <span class="min-w-0">
            <span class="block font-semibold text-surface-900 dark:text-surface-0 truncate">
              {{ item.media.title || item.media.url }}
            </span>
            <span class="block text-sm text-surface-500 dark:text-surface-400">{{ item.media.platform_label }}</span>
          </span>
        </button>
        <MediaPlayerDialog v-model:visible="isPlaying" :media="item.media" />
      </template>
    </div>
  </article>
</template>

<script setup>
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { TYPES_ANNOUNCE_BAND } from '../../../constants/types.js'
import { formatDateCompactWithYear } from '../../../utils/date.js'
import { activitySentenceKey, youtubeThumbnailUrl } from '../../../utils/profileWall.js'
import MediaPlayerDialog from '../../Media/MediaPlayerDialog.vue'

const props = defineProps({
  /** An activity line of the wall: an announce, a publication, a course or a media. */
  item: { type: Object, required: true },
  displayName: { type: String, required: true }
})

const { t } = useI18n()
const isPlaying = ref(false)

const ICONS = {
  announce: 'pi pi-megaphone',
  publication: 'pi pi-file-edit',
  course: 'pi pi-book',
  media: 'pi pi-video'
}
const icon = computed(() => ICONS[props.item.type])

// A YouTube video without a stored thumbnail still has its still.
const thumbnailUrl = computed(() => {
  const media = props.item.media
  if (!media) return null
  return (
    media.thumbnail_url ??
    (media.platform === 'youtube' ? youtubeThumbnailUrl(media.embed_id) : null)
  )
})

// Announces have no page of their own: they are listed further down this profile.
function showAnnounces() {
  document.getElementById('annonces')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>
