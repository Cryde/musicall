<template>
  <div :style="{ paddingLeft: `${folder.depth * 12}px` }" class="flex flex-col gap-1">
    <div
      class="group relative flex items-center h-8 px-2 rounded-md text-sm transition-colors duration-150"
      :class="rowClasses"
      :draggable="true"
      @dragstart="handleDragStart"
      @dragend="handleDragEnd"
      @dragover.prevent="handleDragOver"
      @dragleave="handleDragLeave"
      @drop.prevent="handleDrop"
      @contextmenu="openContextMenu"
    >
      <button
        type="button"
        class="flex items-center gap-2 w-full min-w-0 text-left h-full pr-1 group-hover:pr-20"
        @click="emit('select', folder.id)"
      >
        <i class="pi pi-folder text-surface-500 shrink-0"></i>
        <span class="truncate">{{ folder.name }}</span>
      </button>
      <div
        class="absolute right-1 top-1/2 -translate-y-1/2 hidden group-hover:flex items-center gap-0.5"
      >
        <button
          type="button"
          class="w-6 h-6 flex items-center justify-center rounded text-surface-500 hover:bg-surface-200 dark:hover:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed"
          v-tooltip.top="canCreateSub ? 'Nouveau sous-dossier' : 'Profondeur maximale atteinte'"
          :disabled="!canCreateSub"
          @click.stop="emit('create-sub', folder)"
        >
          <i class="pi pi-plus text-xs"></i>
        </button>
        <button
          type="button"
          class="w-6 h-6 flex items-center justify-center rounded text-surface-500 hover:bg-surface-200 dark:hover:bg-surface-700"
          v-tooltip.top="'Renommer / déplacer'"
          @click.stop="emit('edit', folder)"
        >
          <i class="pi pi-pencil text-xs"></i>
        </button>
        <button
          type="button"
          class="w-6 h-6 flex items-center justify-center rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40"
          v-tooltip.top="'Supprimer'"
          @click.stop="emit('delete', folder)"
        >
          <i class="pi pi-trash text-xs"></i>
        </button>
      </div>
    </div>

    <FolderTreeNode
      v-for="child in folder.children"
      :key="child.id"
      :folder="child"
      :active-id="activeId"
      @select="(id) => emit('select', id)"
      @create-sub="(node) => emit('create-sub', node)"
      @edit="(node) => emit('edit', node)"
      @delete="(node) => emit('delete', node)"
      @drop-on-folder="(payload) => emit('drop-on-folder', payload)"
    />

    <ContextMenu ref="contextMenuRef" :model="contextMenuItems" />
  </div>
</template>

<script setup>
import ContextMenu from 'primevue/contextmenu'
import { computed, ref } from 'vue'
import { canDrop, collectFolderAndDescendants } from '../../../composables/useFolderDragDrop.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const MAX_DEPTH = 6

const props = defineProps({
  folder: { type: Object, required: true },
  activeId: { type: String, default: null }
})

const emit = defineEmits(['select', 'create-sub', 'edit', 'delete', 'drop-on-folder'])

const filesStore = useBandFilesStore()

const isDropTarget = ref(false)
const contextMenuRef = ref(null)

const contextMenuItems = computed(() => [
  { label: 'Ouvrir', icon: 'pi pi-folder-open', command: () => emit('select', props.folder.id) },
  {
    label: 'Nouveau sous-dossier',
    icon: 'pi pi-plus',
    disabled: !canCreateSub.value,
    command: () => emit('create-sub', props.folder)
  },
  { label: 'Renommer / déplacer', icon: 'pi pi-pencil', command: () => emit('edit', props.folder) },
  { separator: true },
  {
    label: 'Supprimer',
    icon: 'pi pi-trash',
    class: 'p-menuitem-danger',
    command: () => emit('delete', props.folder)
  }
])

function openContextMenu(event) {
  event.preventDefault()
  contextMenuRef.value?.show(event)
}

const rowClasses = computed(() => {
  if (isDropTarget.value) {
    return 'bg-primary-100 dark:bg-primary-900/40 ring-2 ring-primary-400 text-surface-900 dark:text-surface-100'
  }
  return props.activeId === props.folder.id
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
})

const canCreateSub = computed(() => (props.folder.depth ?? 0) < MAX_DEPTH - 1)

function handleDragStart(event) {
  // Collect descendant ids (including self) to prevent dropping on a descendant
  const descendantIds = collectFolderAndDescendants([props.folder], props.folder.id)
  filesStore.startDrag({
    type: 'folder',
    id: props.folder.id,
    parentId: props.folder.parent_id ?? null,
    descendantIds
  })
  // Required for Firefox to fire drag events
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', props.folder.id)
}

function handleDragEnd() {
  filesStore.endDrag()
  isDropTarget.value = false
}

function handleDragOver(event) {
  if (!filesStore.dragSource) return
  if (!canDrop(filesStore.dragSource, props.folder.id)) {
    event.dataTransfer.dropEffect = 'none'
    return
  }
  event.dataTransfer.dropEffect = 'move'
  isDropTarget.value = true
}

function handleDragLeave() {
  isDropTarget.value = false
}

function handleDrop() {
  isDropTarget.value = false
  if (!canDrop(filesStore.dragSource, props.folder.id)) return
  emit('drop-on-folder', {
    targetFolderId: props.folder.id,
    source: { ...filesStore.dragSource }
  })
}
</script>
