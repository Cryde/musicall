<template>
  <DiscoverMockup :label="t('home.discover.announces.image_alt')">
    <div class="flex items-center gap-2 h-10 px-4 rounded-lg border border-surface-300 dark:border-surface-600 text-sm text-surface-900 dark:text-surface-0">
      <i class="pi pi-search text-surface-500 dark:text-surface-400" />
      {{ t('home.discover.announces.mock.search') }}
    </div>
    <span class="text-sm text-surface-600 dark:text-surface-300">
      {{ t('home.discover.announces.mock.results', announces.length) }}
    </span>
    <MockupTile v-for="announce in announces" :key="announce.title">
      <span :class="['size-10 shrink-0 rounded-full inline-flex items-center justify-center font-bold text-white', announce.avatar]">
        {{ announce.title[0] }}
      </span>
      <span class="flex flex-col grow min-w-0">
        <span class="font-semibold text-surface-900 dark:text-surface-0">{{ announce.title }}</span>
        <span class="text-sm text-surface-600 dark:text-surface-300">{{ announce.meta }}</span>
      </span>
      <i class="pi pi-envelope text-primary" />
    </MockupTile>
  </DiscoverMockup>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DiscoverMockup from './DiscoverMockup.vue'
import MockupTile from './MockupTile.vue'

const { t } = useI18n()

const announces = computed(() => [
  {
    title: t('home.discover.announces.mock.velvet_title'),
    meta: t('home.discover.announces.mock.velvet_meta'),
    avatar: 'bg-pink-700'
  },
  {
    title: t('home.discover.announces.mock.ondes_title'),
    meta: t('home.discover.announces.mock.ondes_meta'),
    avatar: 'bg-teal-700'
  },
  {
    title: t('home.discover.announces.mock.nuit_title'),
    meta: t('home.discover.announces.mock.nuit_meta'),
    avatar: 'bg-indigo-600'
  }
])
</script>
