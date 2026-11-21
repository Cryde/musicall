<template>
  <!-- Where the panel stands in a song's node (#1431), « Chansons › New 45 », and the way to the song's
       own page, which holds its tab, its demos and its lyrics. -->
  <nav class="flex items-center gap-2 text-sm" :aria-label="t('band_space_files.breadcrumb.aria_label')">
    <i :class="fileSourceIcon('song')" aria-hidden="true"></i>
    <button
      type="button"
      class="shrink-0 text-surface-500 hover:text-surface-800 dark:hover:text-surface-100 hover:underline"
      @click="emit('select', virtualFolderId('song'))"
    >
      {{ sourceName }}
    </button>
    <template v-if="title">
      <i class="pi pi-chevron-right text-xs text-surface-300" aria-hidden="true"></i>
      <span class="min-w-0 truncate font-medium text-surface-800 dark:text-surface-100">{{ title }}</span>
    </template>
    <span class="flex-1" aria-hidden="true"></span>
    <Button
      as="router-link"
      :to="songPageRoute(bandSpaceId, songId)"
      :label="t('band_space_files.song_tree.open_page')"
      icon="pi pi-arrow-right"
      icon-pos="right"
      text
      size="small"
      class="shrink-0"
    />
  </nav>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { fileSourceIcon } from '../../../constants/fileSources.js'
import { virtualFolderId } from '../../../constants/folderSelection.js'
import { songPageRoute } from '../../../utils/songPage.js'

defineProps({
  bandSpaceId: { type: String, required: true },
  songId: { type: String, required: true },
  /** Null once the song has no file left, and so no node to take its title from. */
  title: { type: String, default: null },
  /** The « Chansons » virtual folder's name. */
  sourceName: { type: String, required: true }
})

const emit = defineEmits(['select'])
const { t } = useI18n()
</script>
