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
        @click="emit('select', ROOT_FOLDER_ID)"
      >
        <i class="pi pi-folder text-surface-500"></i>
        <span class="flex-1 truncate">{{ t('band_space_files.folder.root') }}</span>
      </button>
      <Button
        icon="pi pi-plus"
        size="small"
        text
        rounded
        :aria-label="t('band_space_files.folder_edit.title_create')"
        v-tooltip.top="t('band_space_files.folder_edit.title_create')"
        @click="openCreateRoot"
      />
    </div>

    <!-- The folders at the root hang off the same rail as any other level, so the tree reads as one
         hierarchy under « Racine » rather than as a list that happens to sit below it. Their own gap
         lives on each row, not on this container, otherwise it would break the line between them. -->
    <div class="flex flex-col">
      <div v-for="(folder, index) in folders" :key="folder.id" class="flex">
        <FolderTreeRail :is-last="index === folders.length - 1" />

        <div class="min-w-0 flex-1 pt-1">
          <FolderTreeNode
            :folder="folder"
            :active-id="activeFolderId"
            @select="(id) => emit('select', id)"
            @create-sub="openCreateSub"
            @edit="openEdit"
            @delete="openDelete"
            @drop-on-folder="handleDropOnFolder"
          />
        </div>
      </div>
    </div>

    <div
      v-if="virtualFolders.length > 0"
      class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700 flex flex-col gap-1"
    >
      <p class="px-3 text-xs uppercase tracking-wide text-surface-400 mb-1">
        {{ t('band_space_files.folder_tree.sources') }}
      </p>
      <template v-for="virtual in virtualFolders" :key="virtual.id">
        <div
          class="flex items-center rounded-md text-sm transition-colors duration-150"
          :class="virtualButtonClasses(virtual.id)"
        >
          <!-- « Titres » unfolds into the songs that have files (#1431), folded until asked for, so a
               répertoire of forty songs does not push « Setlists » off the panel. -->
          <button
            v-if="hasSongNodes(virtual)"
            type="button"
            class="ml-1 flex h-8 w-6 shrink-0 items-center justify-center rounded text-surface-500 hover:bg-surface-200 dark:hover:bg-surface-700"
            :aria-label="t('band_space_files.song_tree.toggle')"
            :aria-expanded="areSongsUnfolded ? 'true' : 'false'"
            :aria-controls="songListId"
            @click="areSongsUnfolded = !areSongsUnfolded"
          >
            <i
              class="pi text-[10px]"
              :class="areSongsUnfolded ? 'pi-chevron-down' : 'pi-chevron-right'"
              aria-hidden="true"
            ></i>
          </button>
          <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-2 py-2 pr-3 text-left"
            :class="hasSongNodes(virtual) ? 'pl-1' : 'pl-3'"
            @click="emit('select', virtual.id)"
          >
            <i :class="fileSourceIcon(virtual.source)"></i>
            <span class="flex-1 truncate">{{ virtual.name }}</span>
            <span class="text-xs text-surface-500 tabular-nums">{{ virtual.file_count }}</span>
          </button>
        </div>

        <!-- Hung off the same rail as subfolders, under the caret that unfolds them. -->
        <ul
          v-if="hasSongNodes(virtual)"
          v-show="areSongsUnfolded"
          :id="songListId"
          class="flex flex-col pl-2"
        >
          <li v-for="(song, index) in songFolders" :key="song.id" class="flex">
            <FolderTreeRail :is-last="index === songFolders.length - 1" />
            <div class="min-w-0 flex-1 pt-1">
              <button
                type="button"
                class="flex h-8 w-full items-center gap-2 rounded-md px-2 text-left text-sm transition-colors duration-150"
                :class="virtualButtonClasses(songFolderId(song.id))"
                :aria-current="activeFolderId === songFolderId(song.id) ? 'true' : null"
                @click="emit('select', songFolderId(song.id))"
              >
                <span class="flex-1 truncate">{{ song.title }}</span>
                <span class="shrink-0 text-xs text-surface-500 tabular-nums">{{ song.fileCount }}</span>
              </button>
            </div>
          </li>
        </ul>
      </template>
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
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { applyMove, canDrop } from '../../../composables/useFolderDragDrop.js'
import { fileSourceIcon } from '../../../constants/fileSources.js'
import {
  ROOT_FOLDER_ID,
  songFolderId,
  virtualFolderSongId
} from '../../../constants/folderSelection.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import FolderDeleteDialog from './FolderDeleteDialog.vue'
import FolderEditDialog from './FolderEditDialog.vue'
import FolderTreeNode from './FolderTreeNode.vue'
import FolderTreeRail from './FolderTreeRail.vue'

const props = defineProps({
  folders: { type: Array, default: () => [] },
  virtualFolders: { type: Array, default: () => [] },
  /** The songs that have files, by title: the nodes « Titres » unfolds into. */
  songFolders: { type: Array, default: () => [] },
  activeFolderId: { type: String, default: null },
  bandSpaceId: { type: String, default: null },
  isAdmin: { type: Boolean, default: false }
})

const emit = defineEmits(['select'])
const { t } = useI18n()

const songListId = useId()
// Folded by default, unless the panel already stands in one of the songs: the phone's sheet builds the
// tree afresh every time it opens, and the selected song has to be in sight there.
const areSongsUnfolded = ref(virtualFolderSongId(props.activeFolderId) !== null)

function hasSongNodes(virtual) {
  return virtual.source === 'song' && props.songFolders.length > 0
}

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
  move(filesStore.dragSource, null)
}

function move(source, targetFolderId) {
  applyMove(source, targetFolderId, {
    bandSpaceId: props.bandSpaceId,
    filesStore,
    filesApi: bandSpaceFilesApi,
    toast
  })
}

function handleDropOnFolder({ targetFolderId, source }) {
  move(source, targetFolderId)
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
  return props.activeFolderId === ROOT_FOLDER_ID
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
})

function virtualButtonClasses(id) {
  return props.activeFolderId === id
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
}
</script>
