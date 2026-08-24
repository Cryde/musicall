<template>
  <nav class="flex items-center flex-wrap gap-1 text-sm" aria-label="Fil d'Ariane">
    <button
      v-if="segments.length > 0"
      type="button"
      class="text-surface-500 hover:text-surface-800 dark:hover:text-surface-100 hover:underline"
      @click="emit('select', ROOT_FOLDER_ID)"
    >
      Racine
    </button>
    <span
      v-else
      class="font-medium text-surface-800 dark:text-surface-100"
    >
      Racine
    </span>

    <template v-for="(seg, index) in segments" :key="seg.id">
      <i class="pi pi-chevron-right text-xs text-surface-300"></i>
      <button
        v-if="index < segments.length - 1"
        type="button"
        class="text-surface-500 hover:text-surface-800 dark:hover:text-surface-100 hover:underline truncate max-w-[12rem]"
        @click="emit('select', seg.id)"
      >
        {{ seg.name }}
      </button>
      <span
        v-else
        class="font-medium text-surface-800 dark:text-surface-100 truncate max-w-[16rem]"
      >
        {{ seg.name }}
      </span>
    </template>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { isVirtualFolderId, ROOT_FOLDER_ID } from '../../../constants/folderSelection.js'
import { folderPathOf } from '../../../utils/fileListing.js'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  activeFolderId: { type: String, default: null }
})

const emit = defineEmits(['select'])

// The path down from the root, so the root itself has none. Same walk the file rows use to name the
// folder they live in, since a breadcrumb and a location label are the same question asked twice.
const segments = computed(() => {
  if (!props.activeFolderId || props.activeFolderId === ROOT_FOLDER_ID) return []
  if (isVirtualFolderId(props.activeFolderId)) return []

  return folderPathOf(props.folders, props.activeFolderId)
})
</script>
