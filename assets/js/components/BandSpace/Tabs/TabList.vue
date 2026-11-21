<template>
  <ul v-if="compact" class="m-0 p-0 list-none">
    <li
      v-for="tab in tabs"
      :key="tab.id"
      class="border-b border-surface-100 dark:border-surface-800 last:border-b-0"
    >
      <RouterLink
        :to="readerRoute(tab)"
        class="flex items-center gap-3 min-h-14 py-2 text-inherit no-underline"
      >
        <i :class="fileIconClass(tab.mime_type, tab.original_name)" class="text-lg text-surface-500 shrink-0 w-6 text-center" aria-hidden="true"></i>
        <span class="min-w-0 flex-1">
          <span class="block truncate font-medium">{{ tab.original_name }}</span>
          <span class="block truncate text-xs text-surface-500 dark:text-surface-400 tabular-nums">
            {{ folderOf(tab) }} · {{ lastChangeOf(tab) }}
          </span>
        </span>
        <i class="pi pi-chevron-right text-xs text-surface-400 shrink-0" aria-hidden="true"></i>
      </RouterLink>
    </li>
  </ul>

  <div v-else class="text-sm">
    <div
      class="grid grid-cols-12 gap-3 px-3 pb-2 text-xs font-medium text-surface-500 dark:text-surface-400"
      aria-hidden="true"
    >
      <span class="col-span-4">{{ t('band_space_tabs.list.column_name') }}</span>
      <span class="col-span-2">{{ t('band_space_tabs.list.column_folder') }}</span>
      <span class="col-span-2">{{ t('band_space_tabs.list.column_song') }}</span>
      <span class="col-span-2">{{ t('band_space_tabs.list.column_uploader') }}</span>
      <span class="col-span-2">{{ t('band_space_tabs.list.column_updated') }}</span>
    </div>
    <ul class="m-0 p-0 list-none">
      <li v-for="tab in tabs" :key="tab.id" class="border-t border-surface-100 dark:border-surface-800">
        <RouterLink
          :to="readerRoute(tab)"
          class="grid grid-cols-12 gap-3 items-center px-3 py-2.5 rounded-md text-inherit no-underline hover:bg-surface-50 dark:hover:bg-surface-800/40"
        >
          <span class="col-span-4 flex items-center gap-2 min-w-0">
            <i :class="fileIconClass(tab.mime_type, tab.original_name)" class="text-surface-500 shrink-0" aria-hidden="true"></i>
            <span class="truncate font-medium" :title="tab.original_name">{{ tab.original_name }}</span>
          </span>
          <span class="col-span-2 truncate text-surface-600 dark:text-surface-300">{{ folderOf(tab) }}</span>
          <span class="col-span-2 truncate text-surface-600 dark:text-surface-300">{{ songsOf(tab) }}</span>
          <span class="col-span-2 truncate text-surface-600 dark:text-surface-300">{{ tab.created_by?.display_name }}</span>
          <span class="col-span-2 tabular-nums text-surface-600 dark:text-surface-300">{{ lastChangeOf(tab) }}</span>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import { formatDateCompactInYear } from '../../../utils/date.js'
import { fileLocation } from '../../../utils/fileListing.js'
import { fileIconClass } from '../../../utils/fileType.js'
import { tabLastChange, tabSongTitles } from '../../../utils/tabList.js'

/** The Tablatures list (#1430): a row opens the reader. Renaming, moving and deleting stay in Files. */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  tabs: { type: Array, required: true },
  /** From the files store, so an unfiled attachment is placed in « Chansons » as Files places it. */
  virtualFolders: { type: Array, default: () => [] },
  /** The phone's one line per tab. */
  compact: { type: Boolean, default: false }
})

const { t } = useI18n()

function readerRoute(tab) {
  return { name: BAND_SPACE_ROUTES.TAB_READER, params: { id: props.bandSpaceId, fileId: tab.id } }
}

function folderOf(tab) {
  return fileLocation(tab, props.virtualFolders).label
}

function songsOf(tab) {
  return tabSongTitles(tab).join(', ')
}

function lastChangeOf(tab) {
  return formatDateCompactInYear(tabLastChange(tab))
}
</script>
