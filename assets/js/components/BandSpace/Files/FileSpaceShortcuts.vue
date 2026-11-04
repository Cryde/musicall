<template>
  <!-- Under the tree rather than in it: neither is a folder. One is every file in the space whatever
       folder it sits in, the other is what has been deleted. -->
  <div class="flex flex-col gap-1">
    <button
      type="button"
      :class="[ROW, roomy ? 'py-3' : 'py-2', isAllFilesActive ? ACTIVE : IDLE]"
      :aria-current="isAllFilesActive ? 'true' : null"
      v-tooltip.top="roomy ? null : t('band_space_files.page.all_files_hint')"
      @click="emit('select', null)"
    >
      <i class="pi pi-list" aria-hidden="true"></i>
      <span class="flex-1 truncate">{{ t('band_space_files.page.all_files') }}</span>
    </button>

    <button
      type="button"
      :class="[ROW, roomy ? 'py-3' : 'py-2', isTrashActive ? ACTIVE : IDLE]"
      :aria-current="isTrashActive ? 'true' : null"
      @click="emit('select', TRASH_FOLDER_ID)"
    >
      <i class="pi pi-trash" aria-hidden="true"></i>
      <span class="flex-1 truncate">{{ t('band_space_files.page.trash') }}</span>
      <span class="text-xs text-surface-500 tabular-nums">{{ archivedCount }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { TRASH_FOLDER_ID } from '../../../constants/folderSelection.js'

/** « Tous les fichiers » and « Corbeille », in the sidebar on a computer and in the folder sheet on a phone. */
const props = defineProps({
  activeFolderId: { type: String, default: null },
  archivedCount: { type: Number, default: 0 },
  // The phone's taller rows, and no hover hint.
  roomy: { type: Boolean, default: false }
})

const emit = defineEmits(['select'])
const { t } = useI18n()

const ROW =
  'w-full flex items-center gap-2 px-3 rounded-md text-sm text-left transition-colors duration-150'
const ACTIVE =
  'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-200 font-medium'
const IDLE = 'text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-800'

const isAllFilesActive = computed(() => props.activeFolderId === null)
const isTrashActive = computed(() => props.activeFolderId === TRASH_FOLDER_ID)
</script>
