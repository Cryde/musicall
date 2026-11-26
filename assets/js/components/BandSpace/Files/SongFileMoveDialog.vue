<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('band_space_files.song_folders.move_title', { name: file?.original_name ?? '' })"
    v-bind="sheetDialog"
  >
    <ul class="m-0 flex list-none flex-col gap-1 p-0">
      <li v-for="place in places" :key="place.id ?? 'root'" :style="{ paddingInlineStart: `${place.depth * 1.25}rem` }">
        <button
          type="button"
          class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg border-0 bg-transparent px-2 text-left hover:bg-surface-100 disabled:cursor-default disabled:opacity-60 dark:hover:bg-surface-800"
          :disabled="place.id === currentFolderId || moving"
          @click="emit('pick', place.id)"
        >
          <i :class="[place.id === null ? fileSourceIcon('song') : 'pi pi-folder text-surface-500 dark:text-surface-400']" aria-hidden="true" />
          <span class="flex-1 truncate text-sm text-surface-900 dark:text-surface-0">{{ place.name }}</span>
          <span v-if="place.id === currentFolderId" class="text-xs text-surface-600 dark:text-surface-300">
            {{ t('band_space_files.song_folders.current') }}
          </span>
        </button>
      </li>
    </ul>
    <template #footer>
      <Button :label="t('band_space_files.common.cancel')" severity="secondary" text @click="visible = false" />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSheetDialog } from '../../../composables/useSheetDialog.js'
import { fileSourceIcon } from '../../../constants/fileSources.js'
import { songFolderDestinations } from '../../../utils/songFileFolders.js'

/** « Ranger dans un dossier »: where a song's file goes among the song's folders (#1451). */
const props = defineProps({
  file: { type: Object, default: null },
  folders: { type: Array, required: true },
  /** Where the file is now, null at the song's root. */
  currentFolderId: { type: String, default: null },
  moving: { type: Boolean, default: false }
})

const emit = defineEmits(['pick'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const sheetDialog = useSheetDialog('28rem')

const places = computed(() => [
  { id: null, name: t('band_space_files.song_folders.song_root'), depth: 0 },
  ...songFolderDestinations(props.folders)
])
</script>
