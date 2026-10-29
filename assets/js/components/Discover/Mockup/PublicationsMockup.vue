<template>
  <DiscoverMockup :label="t('home.discover.publications.image_alt')">
    <div class="flex flex-wrap gap-2">
      <span
        v-for="filter in filters"
        :key="filter.label"
        :class="[
          'px-3 py-1 rounded-full text-xs font-semibold',
          filter.isActive ? 'bg-primary text-primary-contrast' : 'bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-200'
        ]"
      >
        {{ filter.label }}
      </span>
    </div>
    <MockupTile v-for="publication in PUBLICATIONS" :key="publication.title">
      <img :src="publication.cover" alt="" width="96" height="54" loading="lazy" class="w-24 h-[54px] shrink-0 rounded-lg object-cover" />
      <span class="flex flex-col min-w-0">
        <span class="truncate font-semibold text-surface-900 dark:text-surface-0">{{ publication.title }}</span>
        <span class="text-sm text-surface-600 dark:text-surface-300">
          {{ t('home.discover.publications.mock.discovery_by', { name: publication.author }) }}
        </span>
      </span>
    </MockupTile>
  </DiscoverMockup>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import freevoltCover from '../../../../image/discover/publication-freevolt.webp'
import jardinPublicCover from '../../../../image/discover/publication-jardin-public.webp'
import polarisCover from '../../../../image/discover/publication-polaris.webp'
import DiscoverMockup from './DiscoverMockup.vue'
import MockupTile from './MockupTile.vue'

const { t } = useI18n()

// Real « Découvertes » shared by members on musicall.com, copied here so the page never depends on them staying online.
const PUBLICATIONS = [
  {
    title: 'Jardin Public - Cardio / La piscine LIVE SESSION',
    author: 'Romain',
    cover: jardinPublicCover
  },
  {
    title: 'Polaris - WITHOUT YOU [Official Music Video]',
    author: 'Phonotographer',
    cover: polarisCover
  },
  {
    title: 'FREEVOLT - Rétrospective - 4 années de concerts !',
    author: 'fredox',
    cover: freevoltCover
  }
]

const filters = computed(() => [
  { label: t('home.discover.publications.mock.filter_all'), isActive: false },
  { label: t('home.discover.publications.mock.filter_articles'), isActive: false },
  { label: t('home.discover.publications.mock.filter_courses'), isActive: false },
  { label: t('home.discover.publications.mock.filter_discoveries'), isActive: true }
])
</script>
