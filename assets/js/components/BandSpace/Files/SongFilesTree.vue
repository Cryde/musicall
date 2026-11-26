<template>
  <div class="flex flex-col gap-2">
    <ul v-if="rows.length > 0" class="m-0 flex list-none flex-col gap-1.5 p-0">
      <li
        v-for="row in rows"
        :key="row.key"
        :style="{ paddingInlineStart: `${row.level * indentRem}rem` }"
        @drop.prevent.stop="dropOnRow($event, row)"
      >
        <SongFileFolderRow
          v-if="row.type === 'folder'"
          :node="row.node"
          :is-open="openFolderIds.has(row.node.folder.id)"
          :can-edit="canEdit"
          @toggle="toggle"
          @upload="(folderId) => emitUpload(folderId, [])"
          @link="(folderId) => emit('link', folderId)"
          @create-subfolder="(parent) => openNameDialog({ parent })"
          @rename="(folder) => openNameDialog({ folder })"
          @delete="confirmDelete"
        />
        <div
          v-else-if="row.type === 'file'"
          :draggable="canDrag"
          class="rounded-xl border border-surface-200 bg-surface-50 px-2.5 py-2 dark:border-surface-700 dark:bg-surface-800/60"
          @dragstart="startDrag($event, row.file)"
        >
          <AttachedFileRow
            :band-space-id="bandSpaceId"
            :file="row.file"
            :can-read-tabs="canReadTabs"
            :can-detach="canDetach"
            :can-move="canEdit"
            :back-label="songTitle"
            @detach="emit('detach', $event)"
            @move="openMove"
          />
        </div>
        <p v-else class="m-0 px-2 py-1 text-xs italic text-surface-600 dark:text-surface-300">
          {{ t('band_space_files.song_folders.empty') }}
        </p>
      </li>
    </ul>

    <div v-if="canEdit" class="flex flex-wrap items-center gap-2">
      <template v-if="folders.length === 0">
        <span class="text-xs text-surface-600 dark:text-surface-300">{{ t('band_space_files.song_folders.suggestions_label') }}</span>
        <Button
          v-for="name in suggestedNames"
          :key="name"
          :label="name"
          :aria-label="t('band_space_files.song_folders.suggestion_add', { name })"
          icon="pi pi-plus"
          size="small"
          severity="secondary"
          outlined
          rounded
          :loading="creatingSuggestion === name"
          :disabled="creatingSuggestion !== null"
          @click="createSuggested(name)"
        />
      </template>
      <Button
        :label="t('band_space_files.folder_edit.title_create')"
        icon="pi pi-folder-plus"
        size="small"
        text
        @click="openNameDialog({})"
      />
    </div>

    <SongFileFolderNameDialog
      v-model:visible="nameDialog.visible"
      :header="nameDialogHeader"
      :submit-label="nameDialog.folder ? t('band_space_files.common.save') : t('band_space_files.common.create')"
      :initial-name="nameDialog.folder?.name ?? ''"
      :used-names="usedNames"
      :suggested-names="suggestedNames"
      :sibling-names="nameDialogSiblingNames"
      :saving="nameDialog.saving"
      :error="nameDialog.error"
      @submit="submitName"
    />
    <SongFileMoveDialog
      v-model:visible="moveDialog.visible"
      :file="moveDialog.file"
      :folders="folders"
      :current-folder-id="moveDialog.file ? songFolderIdOf(moveDialog.file, songId) : null"
      :moving="moveDialog.moving"
      @pick="pickDestination"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import songFileFoldersApi from '../../../api/bandSpace/song-file-folders.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { SONG_FILE_DRAG_TYPE } from '../../../constants/songFileDrag.js'
import { songFolderLabel } from '../../../utils/fileListing.js'
import {
  buildSongFileTree,
  songFolderIdOf,
  visibleSongFileRows
} from '../../../utils/songFileFolders.js'
import AttachedFileRow from './AttachedFileRow.vue'
import SongFileFolderNameDialog from './SongFileFolderNameDialog.vue'
import SongFileFolderRow from './SongFileFolderRow.vue'
import SongFileMoveDialog from './SongFileMoveDialog.vue'

/**
 * A song's files sorted into folders (#1451), three levels deep at most. The folders are virtual: putting
 * a file in one never moves it in Files. The section around owns the files and the upload, link and
 * detach dialogs; this owns the folders.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  songId: { type: String, required: true },
  songTitle: { type: String, default: '' },
  /** Every file attached to the song. */
  files: { type: Array, required: true },
  /** The tab card's file, left out of the root list it heads already. */
  featured: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
  canDetach: { type: Boolean, default: false },
  canReadTabs: { type: Boolean, default: false }
})

const emit = defineEmits(['upload', 'link', 'detach', 'file-updated', 'files-changed'])

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const isBelowMd = useIsBelowMd()

const folders = ref([])
/** Every song folder name of the space, read once when a folder is first named. */
const usedNames = ref([])
let usedNamesRequested = false
const openFolderIds = ref(new Set())
const creatingSuggestion = ref(null)
const nameDialog = reactive({
  visible: false,
  parent: null,
  folder: null,
  saving: false,
  error: null
})
const moveDialog = reactive({ visible: false, file: null, moving: false })

const suggestedNames = computed(() => [
  t('band_space_files.song_folders.suggested_tabs'),
  t('band_space_files.song_folders.suggested_stems'),
  t('band_space_files.song_folders.suggested_demos'),
  t('band_space_files.song_folders.suggested_scores')
])
const tree = computed(() => buildSongFileTree(folders.value, props.files, props.songId))
const rows = computed(() => visibleSongFileRows(tree.value, openFolderIds.value, props.featured))
const hasFolders = computed(() => folders.value.length > 0)
// A touch screen has no drag and drop: a phone moves a file through its ⋮.
const canDrag = computed(() => props.canEdit && !isBelowMd.value)
const indentRem = computed(() => (isBelowMd.value ? 0.75 : 1.25))

const nameDialogHeader = computed(() => {
  if (nameDialog.folder) return t('band_space_files.song_folders.rename_title')
  if (nameDialog.parent)
    return t('band_space_files.song_folders.create_in_title', { name: nameDialog.parent.name })
  return t('band_space_files.folder_edit.title_create')
})
const nameDialogSiblingNames = computed(() => {
  const parentId = nameDialog.folder
    ? (nameDialog.folder.parent_id ?? null)
    : (nameDialog.parent?.id ?? null)
  return folders.value
    .filter(
      (folder) => (folder.parent_id ?? null) === parentId && folder.id !== nameDialog.folder?.id
    )
    .map((folder) => folder.name)
})

watch(() => [props.bandSpaceId, props.songId], loadFolders, { immediate: true })

async function loadFolders() {
  try {
    folders.value = await songFileFoldersApi.getSongFolders(props.bandSpaceId, props.songId)
  } catch {
    // Keeps what is on screen: the files still show, at the root at worst.
  }
}

function toggle(folderId) {
  const next = new Set(openFolderIds.value)
  if (next.has(folderId)) next.delete(folderId)
  else next.add(folderId)
  openFolderIds.value = next
}

function open(folderId) {
  if (!folderId || openFolderIds.value.has(folderId)) return
  openFolderIds.value = new Set([...openFolderIds.value, folderId])
}

function emitUpload(folderId, files) {
  open(folderId)
  emit('upload', folderId, files, songFolderLabel(props.songTitle, folders.value, folderId))
}

async function loadUsedNames() {
  if (usedNamesRequested) return
  usedNamesRequested = true
  try {
    const spaceFolders = await songFileFoldersApi.getSpaceSongFolders(props.bandSpaceId)
    usedNames.value = [
      ...new Set([...usedNames.value, ...spaceFolders.map((folder) => folder.name)])
    ]
  } catch {
    usedNamesRequested = false
  }
}

function rememberName(name) {
  if (!usedNames.value.includes(name)) usedNames.value = [...usedNames.value, name]
}

/** `parent` names a new folder inside it, `folder` renames that one, neither creates at the song's root. */
function openNameDialog({ parent = null, folder = null }) {
  Object.assign(nameDialog, { visible: true, parent, folder, saving: false, error: null })
  loadUsedNames()
}

async function submitName(name) {
  nameDialog.saving = true
  nameDialog.error = null
  try {
    if (nameDialog.folder) {
      const renamed = await songFileFoldersApi.renameFolder(
        props.bandSpaceId,
        props.songId,
        nameDialog.folder.id,
        name
      )
      folders.value = folders.value.map((folder) => (folder.id === renamed.id ? renamed : folder))
    } else {
      const created = await songFileFoldersApi.createFolder(props.bandSpaceId, props.songId, {
        name,
        parentId: nameDialog.parent?.id ?? null
      })
      folders.value = [...folders.value, created]
      open(nameDialog.parent?.id ?? null)
    }
    rememberName(name)
    nameDialog.visible = false
  } catch (e) {
    nameDialog.error = e.message
  } finally {
    nameDialog.saving = false
  }
}

async function createSuggested(name) {
  creatingSuggestion.value = name
  try {
    const created = await songFileFoldersApi.createFolder(props.bandSpaceId, props.songId, { name })
    folders.value = [...folders.value, created]
    rememberName(name)
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('band_space_files.song_folders.create_failed'),
      detail: e.message,
      life: 5000
    })
  } finally {
    creatingSuggestion.value = null
  }
}

function confirmDelete(folder) {
  confirm.require({
    message: t('band_space_files.song_folders.delete_confirm', { name: folder.name }),
    header: t('band_space_files.song_folders.delete'),
    icon: 'pi pi-trash',
    acceptLabel: t('band_space_files.common.delete'),
    rejectLabel: t('band_space_files.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await songFileFoldersApi.deleteFolder(props.bandSpaceId, props.songId, folder.id)
        // Its sub-folders went with it, and their files are back at the root: both lists change.
        emit('files-changed')
        await loadFolders()
        toast.add({
          severity: 'success',
          summary: t('band_space_files.song_folders.deleted'),
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: t('band_space_files.song_folders.delete_failed'),
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}

function openMove(file) {
  Object.assign(moveDialog, { visible: true, file, moving: false })
}

async function pickDestination(folderId) {
  moveDialog.moving = true
  await moveFile(moveDialog.file.id, folderId)
  moveDialog.moving = false
  moveDialog.visible = false
}

/** Null puts the file back at the song's root. */
async function moveFile(fileId, folderId) {
  const file = props.files.find((candidate) => candidate.id === fileId)
  if (!file || songFolderIdOf(file, props.songId) === folderId) return
  try {
    const updated = await songFileFoldersApi.moveFile(
      props.bandSpaceId,
      props.songId,
      fileId,
      folderId
    )
    emit('file-updated', updated)
    open(folderId)
    const folder = folders.value.find((candidate) => candidate.id === folderId)
    toast.add({
      severity: 'success',
      summary: folder
        ? t('band_space_files.song_folders.moved', { folder: folder.name })
        : t('band_space_files.song_folders.moved_to_root'),
      life: 3000
    })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: t('band_space_files.song_folders.move_failed'),
      detail: e.message,
      life: 5000
    })
  }
}

function startDrag(event, file) {
  event.dataTransfer.setData(SONG_FILE_DRAG_TYPE, file.id)
  event.dataTransfer.effectAllowed = 'move'
}

/** Every row stands for a folder, or the song's root: a file dropped on it moves there, device files go up into it. */
function dropOnRow(event, row) {
  if (!props.canEdit) return
  const movedFileId = event.dataTransfer?.getData(SONG_FILE_DRAG_TYPE)
  if (movedFileId) {
    moveFile(movedFileId, row.folderId)
    return
  }
  const files = [...(event.dataTransfer?.files ?? [])]
  if (files.length > 0) emitUpload(row.folderId, files)
}

defineExpose({ reload: loadFolders, moveFile, openMove, hasFolders })
</script>
