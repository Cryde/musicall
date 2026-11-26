<template>
  <!-- Where the panel stands in a song's node (#1431), « Titres › New 45 › Stems », and the way to the
       song's own page, which holds its tab, its demos and its lyrics. -->
  <nav class="flex items-center gap-2 text-sm" :aria-label="t('band_space_files.breadcrumb.aria_label')">
    <i :class="fileSourceIcon('song')" aria-hidden="true"></i>
    <button
      type="button"
      class="shrink-0 text-surface-500 hover:text-surface-800 dark:hover:text-surface-100 hover:underline"
      @click="emit('select', virtualFolderId('song'))"
    >
      {{ sourceName }}
    </button>
    <!-- The song, then its own folders (#1451): every step but the last goes back up. -->
    <template v-for="(crumb, index) in crumbs" :key="crumb.selection">
      <i class="pi pi-chevron-right text-xs text-surface-300" aria-hidden="true"></i>
      <span
        v-if="index === crumbs.length - 1"
        class="min-w-0 truncate font-medium text-surface-800 dark:text-surface-100"
      >
        {{ crumb.name }}
      </span>
      <button
        v-else
        type="button"
        class="min-w-0 truncate text-surface-500 hover:text-surface-800 dark:hover:text-surface-100 hover:underline"
        @click="emit('select', crumb.selection)"
      >
        {{ crumb.name }}
      </button>
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
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { fileSourceIcon } from '../../../constants/fileSources.js'
import { songFolderId, virtualFolderId } from '../../../constants/folderSelection.js'
import { songPageRoute } from '../../../utils/songPage.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  songId: { type: String, required: true },
  /** Null once the song has no file left, and so no node to take its title from. */
  title: { type: String, default: null },
  /** The « Titres » virtual folder's name. */
  sourceName: { type: String, required: true },
  /** The song folder the panel stands in, from the song's root down; empty on the song itself. */
  folderPath: { type: Array, default: () => [] }
})

const emit = defineEmits(['select'])
const { t } = useI18n()

const crumbs = computed(() =>
  props.title
    ? [
        { name: props.title, selection: songFolderId(props.songId) },
        ...props.folderPath.map((folder) => ({
          name: folder.name,
          selection: songFolderId(props.songId, folder.id)
        }))
      ]
    : []
)
</script>
