<template>
  <div class="p-4">
    <div v-if="isOwner && media.length" class="flex justify-end mb-3">
      <router-link :to="manageRoute" class="text-sm font-semibold text-primary hover:underline">
        {{ t('profile.wall.media_manage') }}
      </router-link>
    </div>

    <div v-if="!media.length" class="flex flex-col items-center gap-3 py-4 text-center">
      <p class="m-0 text-sm text-surface-500 dark:text-surface-400">
        {{ isOwner ? t('profile.media.empty_own') : t('profile.wall.empty.media') }}
      </p>
      <Button
        v-if="isOwner"
        as="router-link"
        :to="manageRoute"
        :label="t('profile.media.add_creation')"
        icon="pi pi-plus"
        size="small"
      />
    </div>

    <ul v-else class="grid gap-4 sm:grid-cols-2 list-none m-0 p-0">
      <li v-for="(item, index) in media" :key="item.id" :class="{ 'sm:col-span-2': index === 0 }">
        <button
          type="button"
          class="group relative block w-full aspect-video rounded-lg overflow-hidden bg-surface-100 dark:bg-surface-800 cursor-pointer"
          :aria-label="item.title ? t('profile.media.play', { title: item.title }) : t('profile.media.play_untitled')"
          @click="open(item)"
        >
          <!-- The stored thumbnail only: nothing from the platforms loads before a click. -->
          <img v-if="item.thumbnail_url" :src="item.thumbnail_url" alt="" class="w-full h-full object-cover" loading="lazy" />
          <span
            class="absolute top-2 left-2 inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-black/60 text-white text-xs font-semibold"
          >
            <i :class="mediaPlatformIcon(item.platform)" aria-hidden="true" />{{ item.platform_label }}
          </span>
          <span class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/20 transition-colors" aria-hidden="true">
            <span class="w-12 h-12 rounded-full bg-black/60 text-white flex items-center justify-center">
              <i class="pi pi-play" />
            </span>
          </span>
        </button>
        <div class="flex items-start gap-2 mt-2">
          <span class="flex-1 min-w-0 text-sm font-semibold text-surface-900 dark:text-surface-0 truncate">{{ trackLabel(item) }}</span>
          <ReportButton
            v-if="!isOwner"
            target-type="profile_media"
            :target-id="item.id"
            :author-username="ownerUsername"
            :aria-label="item.title ? t('profile.media.report', { title: item.title }) : t('profile.media.report_untitled')"
            icon-only
            class="shrink-0"
          />
        </div>
      </li>
    </ul>

    <MediaPlayerDialog v-model:visible="isPlaying" :media="playing" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { mediaPlatformIcon } from '../../../utils/mediaEmbed.js'
import { trackLabel } from '../../../utils/profileListen.js'
import MediaPlayerDialog from '../../Media/MediaPlayerDialog.vue'
import ReportButton from '../../Report/ReportButton.vue'

/**
 * The wall's « Médias » (#1317): the member's musician and teacher media as a grid, the first one wider. It took
 * over from the musician page.
 */
defineProps({
  media: { type: Array, default: () => [] },
  isOwner: { type: Boolean, default: false },
  ownerUsername: { type: String, required: true },
  /** Where the owner adds and removes them, until they are edited in place. */
  manageRoute: { type: Object, default: null }
})

const { t } = useI18n()
const isPlaying = ref(false)
const playing = ref(null)

function open(item) {
  playing.value = item
  isPlaying.value = true
}
</script>
