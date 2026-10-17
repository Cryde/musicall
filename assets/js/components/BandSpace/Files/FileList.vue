<template>
  <div>
    <div v-if="isLoading && files.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
    </div>

    <div
      v-else-if="files.length === 0 && folders.length === 0"
      class="flex flex-col items-center justify-center py-16 text-center text-surface-400"
    >
      <i class="pi pi-folder-open text-5xl mb-4"></i>
      <p class="text-sm italic">{{ emptyMessage ?? t('band_space_files.file_list.empty') }}</p>
    </div>

    <div v-else class="flex flex-col">
      <div
        class="hidden md:flex items-center gap-2 px-3 py-2 text-xs font-medium uppercase tracking-wide text-surface-400 border-b border-surface-200 dark:border-surface-700"
      >
        <Checkbox
          :model-value="allSelected"
          binary
          size="small"
          class="shrink-0"
          :aria-label="t('band_space_files.common.select_all')"
          @update:model-value="toggleAll"
        />
        <div class="grid grid-cols-12 gap-2 flex-1 min-w-0">
          <div class="col-span-6">{{ t('band_space_files.file_list.column.name') }}</div>
          <div class="col-span-2">{{ t('band_space_files.file_list.column.size') }}</div>
          <div class="col-span-2">{{ t('band_space_files.file_list.column.tags') }}</div>
          <div class="col-span-2">{{ t('band_space_files.file_list.column.added_at') }}</div>
        </div>
        <span class="shrink-0 w-7" aria-hidden="true"></span>
      </div>

      <!-- Folders first, in the same grid as the files, so the panel reads as one list. Focusable and
           operable from the keyboard: #688 established that a clickable div needs this, and unlike the
           file row below it this one has no nested button to conflict with the button role. -->
      <div
        v-for="folder in folders"
        :key="folder.id"
        role="button"
        tabindex="0"
        :aria-label="t('band_space_files.file_list.open_folder', { name: folder.name })"
        class="flex items-center gap-2 px-3 py-2 text-sm border-b border-surface-100 dark:border-surface-800 hover:bg-surface-50 dark:hover:bg-surface-800/40 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-500"
        :class="dropTargetId === folder.id ? 'bg-primary-100 dark:bg-primary-900/40 ring-2 ring-primary-400' : ''"
        :draggable="true"
        @click="emit('open-folder', folder.id)"
        @keydown.enter.prevent="emit('open-folder', folder.id)"
        @keydown.space.prevent="emit('open-folder', folder.id)"
        @dragstart="(event) => handleFolderDragStart(event, folder)"
        @dragend="handleDragEnd"
        @dragover.prevent="(event) => handleFolderDragOver(event, folder)"
        @dragleave="handleFolderDragLeave"
        @drop.prevent="() => handleFolderDrop(folder)"
      >
        <div class="grid grid-cols-12 gap-2 flex-1 min-w-0 items-center">
          <div class="col-span-12 md:col-span-6 flex items-center gap-2 min-w-0">
            <i class="pi pi-folder text-lg text-primary-500 shrink-0" aria-hidden="true"></i>
            <div class="min-w-0">
              <span class="block truncate font-medium">{{ folder.name }}</span>
              <!-- Under the name rather than under the « Taille » header, where a count of files would
                   sit in the column every other row fills with bytes. -->
              <span
                v-if="folderFileCountLabel(folder.file_count)"
                class="block text-xs text-surface-500 dark:text-surface-400"
              >
                {{ folderFileCountLabel(folder.file_count) }}
              </span>
            </div>
          </div>

          <div class="col-span-4 md:col-span-2 text-surface-400">{{ noValue() }}</div>
          <div class="col-span-4 md:col-span-2"></div>

          <div class="col-span-4 md:col-span-2 text-surface-600 dark:text-surface-300">
            {{ formatDate(folder.creation_datetime) }}
          </div>
        </div>

        <span class="shrink-0 w-7 h-7" aria-hidden="true"></span>
      </div>

      <div
        v-for="file in files"
        :key="file.id"
        class="flex items-center gap-2 px-3 py-2 text-sm border-b border-surface-100 dark:border-surface-800 cursor-pointer"
        :class="
          isSelected(file)
            ? 'bg-primary-50 dark:bg-primary-900/30'
            : 'hover:bg-surface-50 dark:hover:bg-surface-800/40'
        "
        :draggable="!hasSelection"
        @click="(event) => handleRowClick(event, file)"
        @contextmenu="(event) => openContextMenu(event, file)"
        @dragstart="(event) => handleDragStart(event, file)"
        @dragend="handleDragEnd"
      >
        <Checkbox
          :model-value="isSelected(file)"
          binary
          size="small"
          class="shrink-0"
          :aria-label="t('band_space_files.common.select_file', { name: file.original_name })"
          @click.stop
          @mousedown="rememberModifiers"
          @keydown="rememberModifiers"
          @update:model-value="() => toggleFromCheckbox(file)"
        />
        <div class="grid grid-cols-12 gap-2 flex-1 min-w-0 items-center">
          <div class="col-span-12 md:col-span-6 flex items-center gap-2 min-w-0">
            <i :class="iconForMime(file.mime_type)" class="text-lg text-surface-500 shrink-0"></i>
            <div class="min-w-0">
              <span class="block truncate font-medium">{{ file.original_name }}</span>
              <!-- Where the file actually lives, for a listing that is not one place. A search result
                   nobody can locate is half an answer, so the folder is a link back to it. -->
              <button
                v-if="showLocation"
                type="button"
                class="flex items-center gap-1 max-w-full text-xs text-surface-500 dark:text-surface-400 hover:underline"
                :aria-label="t('band_space_files.file_list.open_location', { location: locationOf(file).label })"
                @click.stop="emit('open-location', locationOf(file).folderId)"
              >
                <i class="pi pi-folder text-[10px] shrink-0" aria-hidden="true"></i>
                <span class="truncate">{{ locationOf(file).label }}</span>
              </button>
            </div>
          </div>

          <div class="col-span-4 md:col-span-2 tabular-nums text-surface-600 dark:text-surface-300">
            {{ formatBytes(file.size) }}
          </div>

          <div class="col-span-4 md:col-span-2 flex flex-wrap gap-1">
            <Tag
              v-for="tag in file.tags"
              :key="tag.id"
              :value="tag.name"
              :style="tagStyle(tag.color_hex)"
              class="text-xs"
            />
          </div>

          <div class="col-span-4 md:col-span-2 text-surface-600 dark:text-surface-300">
            {{ formatDate(file.creation_datetime) }}
          </div>
        </div>

        <button
          type="button"
          class="shrink-0 w-7 h-7 flex items-center justify-center rounded text-surface-500 hover:bg-surface-200 dark:hover:bg-surface-700"
          :aria-label="t('band_space_files.file_list.file_actions')"
          aria-haspopup="menu"
          @click.stop="(event) => openContextMenu(event, file)"
        >
          <i class="pi pi-ellipsis-v text-xs" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <ContextMenu ref="contextMenuRef" :model="contextMenuItems" />
  </div>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import ContextMenu from 'primevue/contextmenu'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import {
  applyMove,
  canDrop,
  collectFolderAndDescendants
} from '../../../composables/useFolderDragDrop.js'
import { deleteBlockingAttachments, fileSourceDetachHint } from '../../../constants/fileSources.js'
import { intlLocale, noValue } from '../../../i18n/formatLocale.js'
import { useBandSpaceStore } from '../../../store/bandSpace/bandSpace.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { isFileCreatorOrAdmin } from '../../../utils/bandSpaceFilePermissions.js'
import { fileLocation, folderFileCountLabel } from '../../../utils/fileListing.js'
import { areAllSelected, selectionAfterClick } from '../../../utils/fileSelection.js'
import { formatBytes } from '../../../utils/formatBytes.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  files: { type: Array, required: true },
  isLoading: { type: Boolean, default: false },
  /** Defaults to the empty folder sentence. */
  emptyMessage: { type: String, default: null },
  /** Direct subfolders of the folder being shown, rendered above the files. */
  folders: { type: Array, default: () => [] },
  /** Show each file's folder, for a listing whose rows can come from anywhere. */
  showLocation: { type: Boolean, default: false }
})

const emit = defineEmits([
  'select',
  'open-share',
  'open-versions',
  'open-rename',
  'open-move',
  'open-folder',
  'open-location'
])

const { t } = useI18n()
const filesStore = useBandFilesStore()
const userSecurityStore = useUserSecurityStore()
const bandSpaceStore = useBandSpaceStore()
const confirm = useConfirm()
const toast = useToast()

const isAdmin = computed(() => bandSpaceStore.getById(props.bandSpaceId)?.role === 'admin')

const dropTargetId = ref(null)

const orderedIds = computed(() => props.files.map((file) => file.id))
const hasSelection = computed(() => filesStore.selectedFileIds.size > 0)
const allSelected = computed(() =>
  areAllSelected(orderedIds.value, [...filesStore.selectedFileIds])
)

function isSelected(file) {
  return filesStore.selectedFileIds.has(file.id)
}

/**
 * A bare click opens the file, as it always has. Ctrl/Cmd and Shift are what turn a click into
 * selection, so the default gesture on a file list keeps meaning "show me this one".
 */
function handleRowClick(event, file) {
  if (event.ctrlKey || event.metaKey || event.shiftKey) {
    applySelection(file, event.shiftKey)

    return
  }

  emit('select', file)
}

function applySelection(file, shift) {
  const { selected, anchorId } = selectionAfterClick({
    id: file.id,
    orderedIds: orderedIds.value,
    selected: [...filesStore.selectedFileIds],
    anchorId: filesStore.selectionAnchorId,
    shift
  })

  filesStore.setSelection(selected, anchorId)
}

/**
 * PrimeVue's Checkbox keeps its own internal state, so the toggle has to arrive through its model
 * event rather than a raw click. Binding @click as well left the two one click out of phase: the row
 * highlighted while the box stayed empty, then the box ticked on the click that deselected the row.
 *
 * Shift is read on mousedown and keydown, both of which run before the model event does.
 */
let shiftHeld = false

function rememberModifiers(event) {
  shiftHeld = event.shiftKey === true
}

function toggleFromCheckbox(file) {
  applySelection(file, shiftHeld)
  shiftHeld = false
}

function toggleAll(checked) {
  // The list pages, so this is every row on screen and never the ones still unloaded. The bar says
  // how many are selected rather than implying the rest.
  filesStore.setSelection(checked ? orderedIds.value : [], null)
}

const contextMenuRef = ref(null)
const contextMenuFile = ref(null)

const canDeleteContextFile = computed(() =>
  isFileCreatorOrAdmin(contextMenuFile.value, userSecurityStore.userProfile?.id, isAdmin.value)
)

const contextFileSourceLabel = computed(() => {
  const attachments = deleteBlockingAttachments(contextMenuFile.value?.attachments)
  if (attachments.length === 0) return null
  if (attachments.length > 1) {
    return t('band_space_files.file_list.detach_from_each')
  }

  return fileSourceDetachHint(attachments[0]?.source_type)
})

const contextMenuItems = computed(() => {
  const f = contextMenuFile.value
  if (!f) return []
  const isAttached = deleteBlockingAttachments(f.attachments).length > 0
  const deleteLabel = isAttached
    ? t('band_space_files.file_list.delete_blocked', { reason: contextFileSourceLabel.value })
    : t('band_space_files.common.delete')
  const items = [
    {
      label: t('band_space_files.file_list.menu.open'),
      icon: 'pi pi-eye',
      command: () => emit('select', f)
    },
    {
      label: t('band_space_files.file_list.menu.download'),
      icon: 'pi pi-download',
      command: () => f.download_url && window.open(f.download_url, '_blank', 'noopener')
    },
    {
      label: t('band_space_files.file_list.menu.rename'),
      icon: 'pi pi-pencil',
      command: () => emit('open-rename', f)
    }
  ]
  if (isAdmin.value) {
    items.push({
      label: t('band_space_files.file_list.menu.share'),
      icon: 'pi pi-share-alt',
      command: () => emit('open-share', f)
    })
  }
  items.push(
    {
      label: t('band_space_files.file_list.menu.versions'),
      icon: 'pi pi-history',
      command: () => emit('open-versions', f)
    },
    {
      label: t('band_space_files.file_move.submit'),
      icon: 'pi pi-arrows-h',
      command: () => emit('open-move', f)
    },
    { separator: true },
    {
      label: deleteLabel,
      icon: 'pi pi-trash',
      class: 'p-menuitem-danger',
      disabled: !canDeleteContextFile.value || isAttached,
      command: () => confirmDelete(f)
    }
  )
  return items
})

function openContextMenu(event, file) {
  event.preventDefault()
  contextMenuFile.value = file
  contextMenuRef.value?.show(event)
}

function confirmDelete(file) {
  confirm.require({
    message: t('band_space_files.file_list.trash_confirm.message', {
      name: file.original_name,
      days: filesStore.trashRetentionDays
    }),
    header: t('band_space_files.file_list.trash_confirm.header'),
    icon: 'pi pi-trash',
    acceptLabel: t('band_space_files.common.delete'),
    rejectLabel: t('band_space_files.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await filesStore.deleteFile(props.bandSpaceId, file.id)
        toast.add({
          severity: 'success',
          summary: t('band_space_files.file_list.trash_confirm.done'),
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('band_space_files.common.delete_failed'),
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}

function handleFolderDragStart(event, folder) {
  filesStore.startDrag({
    type: 'folder',
    id: folder.id,
    parentId: folder.parent_id ?? null,
    // Without the descendants a folder could be dropped inside itself, which would orphan the subtree.
    descendantIds: collectFolderAndDescendants(filesStore.folders, folder.id)
  })
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', folder.id)
}

function handleFolderDragOver(event, folder) {
  if (!filesStore.dragSource) return
  if (!canDrop(filesStore.dragSource, folder.id)) {
    event.dataTransfer.dropEffect = 'none'
    return
  }
  event.dataTransfer.dropEffect = 'move'
  dropTargetId.value = folder.id
}

function handleFolderDragLeave() {
  dropTargetId.value = null
}

function handleFolderDrop(folder) {
  dropTargetId.value = null
  if (!filesStore.dragSource || !canDrop(filesStore.dragSource, folder.id)) return
  applyMove(filesStore.dragSource, folder.id, {
    bandSpaceId: props.bandSpaceId,
    filesStore,
    filesApi: bandSpaceFilesApi,
    toast
  })
}

function handleDragStart(event, file) {
  filesStore.startDrag({
    type: 'file',
    id: file.id,
    folderId: file.folder_id ?? null,
    // Read by canDrop(), which refuses « Racine » for an attached file: the root lists no attachments,
    // so dropping one there takes it out of the tree instead of moving it.
    attachments: file.attachments ?? []
  })
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', file.id)
}

function handleDragEnd() {
  dropTargetId.value = null
  filesStore.endDrag()
}

function iconForMime(mime) {
  if (!mime) return 'pi pi-file'
  if (mime.startsWith('audio/')) return 'pi pi-volume-up'
  if (mime.startsWith('image/')) return 'pi pi-image'
  if (mime.startsWith('video/')) return 'pi pi-video'
  if (mime === 'application/pdf') return 'pi pi-file-pdf'
  return 'pi pi-file'
}

function formatDate(iso) {
  if (!iso) return noValue()
  const date = new Date(iso)
  return date.toLocaleDateString(intlLocale(), { day: '2-digit', month: 'short', year: 'numeric' })
}

/**
 * Where the row says the file lives, and where its link goes. The virtual folders come from the store so
 * an unfiled attachment is named « Notes » the way the sidebar names it, rather than « Racine », which
 * is a listing that excludes attachments.
 */
function locationOf(file) {
  return fileLocation(file, filesStore.virtualFolders)
}

function tagStyle(colorHex) {
  if (!colorHex) return {}
  return { backgroundColor: colorHex, color: '#fff' }
}
</script>
