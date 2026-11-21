<template>
  <!-- Where a row lives, then the songs it belongs to (#1431): « Compo · New 45 ». The place opens in
       the panel, a song on its own page. Neither click opens the row's file. -->
  <span class="flex min-w-0 max-w-full items-center gap-1 text-xs text-surface-500 dark:text-surface-400">
    <button
      type="button"
      class="flex min-w-0 items-center gap-1 hover:underline"
      :aria-label="t('band_space_files.file_list.open_location', { location: location.label })"
      @click.stop="emit('open', location.folderId)"
    >
      <i class="pi pi-folder text-[10px] shrink-0" aria-hidden="true"></i>
      <span class="truncate">{{ location.label }}</span>
    </button>
    <template v-for="song in location.songs" :key="song.id">
      <span class="shrink-0" aria-hidden="true">{{ separator() }}</span>
      <RouterLink
        :to="songPageRoute(bandSpaceId, song.id)"
        class="flex min-w-0 items-center gap-1 font-medium text-emerald-700 hover:underline dark:text-emerald-400"
        :aria-label="t('band_space_files.song_tree.open_song', { title: song.title })"
        @click.stop
      >
        <i :class="fileSourceIcon('song')" class="text-[10px] shrink-0" aria-hidden="true"></i>
        <span class="truncate">{{ song.title }}</span>
      </RouterLink>
    </template>
  </span>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { fileSourceIcon } from '../../../constants/fileSources.js'
import { separator } from '../../../i18n/formatLocale.js'
import { songPageRoute } from '../../../utils/songPage.js'

/** A file row's location line, in a listing whose rows can come from anywhere. */
defineProps({
  bandSpaceId: { type: String, required: true },
  /** From fileLocation(): the place, and the songs the file is attached to. */
  location: { type: Object, required: true }
})

const emit = defineEmits(['open'])
const { t } = useI18n()
</script>
