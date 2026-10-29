<template>
  <DiscoverMockup :label="t('home.discover.photos.image_alt')">
    <div class="flex items-baseline justify-between gap-3">
      <span class="truncate font-bold text-surface-900 dark:text-surface-0">{{ GALLERY_TITLE }}</span>
      <span class="shrink-0 text-sm text-surface-600 dark:text-surface-300">
        {{ t('home.discover.photos.mock.meta', { count: GALLERY_PHOTO_COUNT, name: GALLERY_AUTHOR }) }}
      </span>
    </div>
    <div class="grid grid-cols-3 grid-rows-3 gap-2 h-72">
      <img
        v-for="(photo, index) in PHOTOS"
        :key="photo"
        :src="photo"
        alt=""
        loading="lazy"
        :class="['size-full rounded-lg object-cover', index === 0 ? 'col-span-2 row-span-2' : '']"
      />
    </div>
  </DiscoverMockup>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import photo1 from '../../../../image/discover/gallery-parkway-drive-1.webp'
import photo2 from '../../../../image/discover/gallery-parkway-drive-2.webp'
import photo3 from '../../../../image/discover/gallery-parkway-drive-3.webp'
import photo4 from '../../../../image/discover/gallery-parkway-drive-4.webp'
import photo5 from '../../../../image/discover/gallery-parkway-drive-5.webp'
import photo6 from '../../../../image/discover/gallery-parkway-drive-6.webp'
import DiscoverMockup from './DiscoverMockup.vue'

const { t } = useI18n()

// A real gallery from musicall.com, copied here so the page never depends on it staying online.
const GALLERY_TITLE = 'Parkway Drive - Forest National, Bruxelles'
const GALLERY_AUTHOR = 'JamesKBarbosa'
const GALLERY_PHOTO_COUNT = 24
const PHOTOS = [photo1, photo2, photo3, photo4, photo5, photo6]
</script>
