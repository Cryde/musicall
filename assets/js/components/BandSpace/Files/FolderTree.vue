<template>
  <div class="flex flex-col gap-1">
    <div
      class="flex items-center justify-between gap-2 mb-1 px-2 rounded-md transition-colors duration-150"
      :class="rootDropClasses"
      @dragover.prevent="handleRootDragOver"
      @dragleave="handleRootDragLeave"
      @drop.prevent="handleRootDrop"
    >
      <button
        type="button"
        class="flex items-center gap-2 flex-1 min-w-0 px-1 py-1.5 rounded-md text-sm text-left transition-colors duration-150"
        :class="rootButtonClasses"
        @click="emit('select', null)"
      >
        <i class="pi pi-folder text-surface-500"></i>
        <span class="flex-1 truncate">Tous les fichiers</span>
      </button>
      <Button
        icon="pi pi-plus"
        size="small"
        text
        rounded
        aria-label="Nouveau dossier"
        v-tooltip.top="'Nouveau dossier'"
        @click="openCreateRoot"
      />
    </div>

    <FolderTreeNode
      v-for="folder in folders"
      :key="folder.id"
      :folder="folder"
      :active-id="activeFolderId"
      @select="(id) => emit('select', id)"
      @create-sub="openCreateSub"
      @edit="openEdit"
      @delete="openDelete"
      @drop-on-folder="handleDropOnFolder"
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

    <FolderEditDialog
      v-if="bandSpaceId && editDialogVisible"
      v-model:visible="editDialogVisible"
      :band-space-id="bandSpaceId"
      :mode="editMode"
      :folder="editTarget"
      :parent-id="editParentId"
    />

    <FolderDeleteDialog
      v-if="bandSpaceId && deleteDialogVisible && deleteTarget"
      v-model:visible="deleteDialogVisible"
      :band-space-id="bandSpaceId"
      :folder="deleteTarget"
      :is-admin="isAdmin"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { canDrop } from '../../../composables/useFolderDragDrop.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import FolderDeleteDialog from './FolderDeleteDialog.vue'
import FolderEditDialog from './FolderEditDialog.vue'
import FolderTreeNode from './FolderTreeNode.vue'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  virtualFolders: { type: Array, default: () => [] },
  activeFolderId: { type: String, default: null },
  bandSpaceId: { type: String, default: null },
  isAdmin: { type: Boolean, default: false }
})

const emit = defineEmits(['select'])

const filesStore = useBandFilesStore()
const toast = useToast()

const editDialogVisible = ref(false)
const editMode = ref('create-root')
const editTarget = ref(null)
const editParentId = ref(null)

const deleteDialogVisible = ref(false)
const deleteTarget = ref(null)

const isRootDropTarget = ref(false)

const rootDropClasses = computed(() =>
  isRootDropTarget.value ? 'bg-primary-100 dark:bg-primary-900/40 ring-2 ring-primary-400' : ''
)

function handleRootDragOver(event) {
  if (!filesStore.dragSource) return
  if (!canDrop(filesStore.dragSource, null)) {
    event.dataTransfer.dropEffect = 'none'
    return
  }
  event.dataTransfer.dropEffect = 'move'
  isRootDropTarget.value = true
}

function handleRootDragLeave() {
  isRootDropTarget.value = false
}

function handleRootDrop() {
  isRootDropTarget.value = false
  if (!filesStore.dragSource || !canDrop(filesStore.dragSource, null)) return
  applyMove(filesStore.dragSource, null)
}

function handleDropOnFolder({ targetFolderId, source }) {
  applyMove(source, targetFolderId)
}

async function applyMove(source, targetFolderId) {
  if (!props.bandSpaceId) return
  try {
    if (source.type === 'folder') {
      await filesStore.updateFolder(props.bandSpaceId, source.id, { parent_id: targetFolderId })
      toast.add({
        severity: 'success',
        summary: 'Dossier déplacé',
        life: 2500
      })
    } else if (source.type === 'file') {
      await bandSpaceFilesApi.updateFile(props.bandSpaceId, source.id, {
        folder_id: targetFolderId
      })
      filesStore.fetchFiles(props.bandSpaceId)
      toast.add({
        severity: 'success',
        summary: 'Fichier déplacé',
        life: 2500
      })
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Déplacement impossible',
      detail: e.message,
      life: 5000
    })
  } finally {
    filesStore.endDrag()
  }
}

function openCreateRoot() {
  editMode.value = 'create-root'
  editTarget.value = null
  editParentId.value = null
  editDialogVisible.value = true
}

function openCreateSub(folder) {
  editMode.value = 'create-sub'
  editTarget.value = null
  editParentId.value = folder.id
  editDialogVisible.value = true
}

function openEdit(folder) {
  editMode.value = 'edit'
  editTarget.value = folder
  editParentId.value = null
  editDialogVisible.value = true
}

function openDelete(folder) {
  deleteTarget.value = folder
  deleteDialogVisible.value = true
}

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
  if (source === 'note') return 'pi pi-file-edit text-purple-500'
  if (source === 'song') return 'pi pi-headphones text-emerald-600'
  if (source === 'setlist') return 'pi pi-list text-rose-600'
  return 'pi pi-folder text-surface-500'
}
</script>
