<template>
  <div class="flex flex-col gap-1">
    <button
      type="button"
      class="flex items-center gap-2 px-3 py-2 rounded-md text-sm text-left transition-colors duration-150"
      :class="rootButtonClasses"
      @click="emit('select', null)"
    >
      <i class="pi pi-folder text-surface-500"></i>
      <span class="flex-1 truncate">Tous les fichiers</span>
    </button>

    <FolderTreeNode
      v-for="folder in folders"
      :key="folder.id"
      :folder="folder"
      :active-id="activeFolderId"
      @select="(id) => emit('select', id)"
    />

    <div
      v-if="virtualFolders.length > 0"
      class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700 flex flex-col gap-1"
    >
      <p class="px-3 text-xs uppercase tracking-wide text-surface-400 mb-1">Sources</p>
      <button
        v-for="virtual in virtualFolders"
        :key="virtual.id"
        type="button"
        class="flex items-center gap-2 px-3 py-2 rounded-md text-sm text-left transition-colors duration-150"
        :class="virtualButtonClasses(virtual.id)"
        @click="emit('select', virtual.id)"
      >
        <i :class="virtualIcon(virtual.source)"></i>
        <span class="flex-1 truncate">{{ virtual.name }}</span>
        <span class="text-xs text-surface-500 tabular-nums">{{ virtual.file_count }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import FolderTreeNode from './FolderTreeNode.vue'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  virtualFolders: { type: Array, default: () => [] },
  activeFolderId: { type: String, default: null }
})

const emit = defineEmits(['select'])

const rootButtonClasses = computed(() => {
  return props.activeFolderId === null
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
})

function virtualButtonClasses(id) {
  return props.activeFolderId === id
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
}

function virtualIcon(source) {
  if (source === 'task') return 'pi pi-check-square text-blue-500'
  if (source === 'finance') return 'pi pi-euro text-amber-600'
  return 'pi pi-folder text-surface-500'
}
</script>
