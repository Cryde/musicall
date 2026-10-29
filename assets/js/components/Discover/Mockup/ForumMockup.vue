<template>
  <DiscoverMockup :label="t('home.discover.forum.image_alt')">
    <span class="text-sm font-bold uppercase tracking-wider text-green-700 dark:text-green-400">
      {{ t('home.discover.forum.mock.category') }}
    </span>
    <MockupTile v-for="topic in topics" :key="topic.title">
      <i :class="[topic.isPinned ? 'pi pi-thumbtack text-green-700 dark:text-green-400' : 'pi pi-comments text-surface-500 dark:text-surface-400']" />
      <span class="flex flex-col grow min-w-0">
        <span class="truncate font-semibold text-surface-900 dark:text-surface-0">{{ topic.title }}</span>
        <span class="text-sm text-surface-600 dark:text-surface-300">
          {{ t('home.discover.forum.mock.last_post', { name: topic.lastAuthor }) }}
        </span>
      </span>
      <span class="shrink-0 text-sm font-semibold text-surface-700 dark:text-surface-200">
        {{ t('home.discover.forum.mock.replies', topic.replies) }}
      </span>
    </MockupTile>
  </DiscoverMockup>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DiscoverMockup from './DiscoverMockup.vue'
import MockupTile from './MockupTile.vue'

const { t } = useI18n()

const topics = computed(() => [
  {
    title: t('home.discover.forum.mock.welcome_title'),
    lastAuthor: 'Léa',
    replies: 1,
    isPinned: true
  },
  {
    title: t('home.discover.forum.mock.amp_title'),
    lastAuthor: 'Tom',
    replies: 12,
    isPinned: false
  },
  {
    title: t('home.discover.forum.mock.buzz_title'),
    lastAuthor: 'Sam',
    replies: 8,
    isPinned: false
  },
  {
    title: t('home.discover.forum.mock.compressor_title'),
    lastAuthor: 'Nico',
    replies: 23,
    isPinned: false
  }
])
</script>
