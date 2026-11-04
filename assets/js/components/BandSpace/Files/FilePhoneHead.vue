<template>
  <div class="flex flex-col gap-3 pb-3">
    <div class="flex items-center gap-1">
      <h1 class="m-0 flex-1 text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('band_space_files.phone.title') }}
      </h1>
      <Button
        icon="pi pi-folder-plus"
        text
        rounded
        severity="secondary"
        :aria-label="t('band_space_files.folder_edit.title_create')"
        :disabled="!canCreateFolder"
        @click="emit('create-folder')"
      />
      <Button
        v-if="withFilters"
        :icon="selecting ? 'pi pi-times' : 'pi pi-check-square'"
        text
        rounded
        severity="secondary"
        :aria-label="t('band_space_files.phone.select')"
        :aria-pressed="selecting"
        @click="toggleSelecting"
      />
    </div>

    <!-- Where the panel stands, and the way to anywhere else: the sidebar, folded into one button. -->
    <div class="flex items-center gap-1">
      <button
        type="button"
        class="flex h-11 min-w-0 flex-1 items-center gap-2.5 rounded-xl border border-surface-300 bg-surface-0 px-3.5 text-left text-sm font-medium text-surface-900 dark:border-surface-600 dark:bg-surface-900 dark:text-surface-0"
        aria-haspopup="dialog"
        :aria-label="t('band_space_files.phone.change_location', { location: locationLabel })"
        @click="isLocationSheetOpen = true"
      >
        <i :class="locationIcon" class="text-surface-500 dark:text-surface-400" aria-hidden="true"></i>
        <span class="min-w-0 flex-1 truncate">{{ locationLabel }}</span>
        <i class="pi pi-chevron-down text-xs text-surface-500" aria-hidden="true"></i>
      </button>
      <Button
        v-if="currentFolder"
        icon="pi pi-ellipsis-v"
        text
        rounded
        severity="secondary"
        :aria-label="t('band_space_files.folder_tree.actions')"
        aria-haspopup="dialog"
        @click="isFolderSheetOpen = true"
      />
    </div>

    <template v-if="withFilters">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
          :model-value="filters.query"
          type="search"
          :placeholder="t('band_space_files.filter.search_placeholder')"
          :aria-label="t('band_space_files.filter.search_placeholder')"
          class="w-full"
          @update:model-value="(value) => emit('update-filter', { key: 'query', value })"
        />
      </IconField>

      <div class="-mx-4 flex gap-2 overflow-x-auto px-4">
        <button
          v-for="chip in chips"
          :key="chip.key"
          type="button"
          :class="[PHONE_CHIP, chip.active ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
          aria-haspopup="dialog"
          @click="isFilterSheetOpen = true"
        >
          {{ chip.label }}
          <i class="pi pi-chevron-down text-[10px]" aria-hidden="true"></i>
        </button>
      </div>
    </template>

    <BottomSheet v-model:visible="isLocationSheetOpen" :title="t('band_space_files.page.folders')">
      <div class="flex flex-col gap-3 px-3 pb-4">
        <FolderTree
          :folders="folders"
          :virtual-folders="virtualFolders"
          :active-folder-id="activeFolderId"
          :band-space-id="bandSpaceId"
          :is-admin="isAdmin"
          @select="selectLocation"
        />
        <div class="border-t border-surface-200 pt-3 dark:border-surface-700">
          <FileSpaceShortcuts
            :active-folder-id="activeFolderId"
            :archived-count="archivedCount"
            roomy
            @select="selectLocation"
          />
        </div>
      </div>
    </BottomSheet>

    <BottomSheet
      v-if="currentFolder"
      v-model:visible="isFolderSheetOpen"
      :title="currentFolder.name"
    >
      <SheetAction icon="pi-plus" :disabled="!canHoldSubfolder(currentFolder)" @click="openFolderDialog('create-sub')">
        {{ t('band_space_files.folder_edit.title_create_sub') }}
      </SheetAction>
      <SheetAction icon="pi-pencil" @click="openFolderDialog('edit')">
        {{ t('band_space_files.folder_tree.rename_move') }}
      </SheetAction>
      <SheetAction icon="pi-trash" danger @click="openFolderDialog('delete')">
        {{ t('band_space_files.common.delete') }}
      </SheetAction>
    </BottomSheet>

    <FileFilterSheet
      v-model:visible="isFilterSheetOpen"
      :filters="filters"
      :tags="tags"
      @update-filter="(change) => emit('update-filter', change)"
    />

    <FolderEditDialog
      v-if="folderEdit"
      v-model:visible="isFolderEditOpen"
      :band-space-id="bandSpaceId"
      :mode="folderEdit.mode"
      :folder="folderEdit.folder"
      :parent-id="folderEdit.parentId"
    />
    <FolderDeleteDialog
      v-if="folderToDelete"
      v-model:visible="isFolderDeleteOpen"
      :band-space-id="bandSpaceId"
      :folder="folderToDelete"
      :is-admin="isAdmin"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { fileSourceIcon } from '../../../constants/fileSources.js'
import {
  listedFolderId,
  NO_FOLDER_LISTED,
  TRASH_FOLDER_ID,
  virtualFolderSource
} from '../../../constants/folderSelection.js'
import { PHONE_CHIP, PHONE_CHIP_OFF, PHONE_CHIP_ON } from '../../../constants/phoneControls.js'
import {
  DEFAULT_FILE_SORT,
  fileFilterChipLabels,
  fileSortValue
} from '../../../utils/fileFilters.js'
import { activeLocationLabel, canHoldSubfolder, findFolder } from '../../../utils/fileListing.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'
import FileFilterSheet from './FileFilterSheet.vue'
import FileSpaceShortcuts from './FileSpaceShortcuts.vue'
import FolderDeleteDialog from './FolderDeleteDialog.vue'
import FolderEditDialog from './FolderEditDialog.vue'
import FolderTree from './FolderTree.vue'

/**
 * The Files panel's head on a phone, in the place of the sidebar, the breadcrumb and the toolbar: where
 * the panel stands as one button opening the folders, the search, and the filters as chips.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  isAdmin: { type: Boolean, default: false },
  folders: { type: Array, default: () => [] },
  virtualFolders: { type: Array, default: () => [] },
  activeFolderId: { type: String, default: null },
  archivedCount: { type: Number, default: 0 },
  filters: { type: Object, required: true },
  tags: { type: Array, default: () => [] },
  // The trash has no search, no filters and its own checkboxes, as on a computer.
  withFilters: { type: Boolean, default: true },
  canCreateFolder: { type: Boolean, default: true }
})

const emit = defineEmits(['select-folder', 'update-filter', 'create-folder'])
const selecting = defineModel('selecting', { type: Boolean, default: false })

const { t } = useI18n()

const isLocationSheetOpen = ref(false)
const isFolderSheetOpen = ref(false)
const isFilterSheetOpen = ref(false)
const isFolderEditOpen = ref(false)
const isFolderDeleteOpen = ref(false)
const folderEdit = ref(null)
const folderToDelete = ref(null)

const locationLabel = computed(() =>
  activeLocationLabel(props.activeFolderId, props.folders, props.virtualFolders)
)

const locationIcon = computed(() => {
  if (props.activeFolderId === null) return 'pi pi-list'
  if (props.activeFolderId === TRASH_FOLDER_ID) return 'pi pi-trash'
  const source = virtualFolderSource(props.activeFolderId)

  return source !== null ? fileSourceIcon(source) : 'pi pi-folder'
})

/** The real folder the panel is inside, whose actions the ⋮ holds; none at the root or elsewhere. */
const currentFolder = computed(() => {
  const folderId = listedFolderId(props.activeFolderId)
  if (folderId === null || folderId === NO_FOLDER_LISTED) return null

  return findFolder(props.folders, folderId)
})

const chips = computed(() => {
  const labels = fileFilterChipLabels(props.filters, props.tags)

  return [
    { key: 'tag', label: labels.tag, active: props.filters.tagId !== null },
    { key: 'type', label: labels.type, active: props.filters.mime !== null },
    {
      key: 'sort',
      label: labels.sort,
      active: fileSortValue(props.filters) !== fileSortValue(DEFAULT_FILE_SORT)
    }
  ]
})

function toggleSelecting() {
  selecting.value = !selecting.value
}

function selectLocation(folderId) {
  isLocationSheetOpen.value = false
  emit('select-folder', folderId)
}

function openFolderDialog(action) {
  const folder = currentFolder.value
  isFolderSheetOpen.value = false
  if (!folder) return

  if (action === 'delete') {
    folderToDelete.value = folder
    isFolderDeleteOpen.value = true
    return
  }
  folderEdit.value =
    action === 'edit'
      ? { mode: 'edit', folder, parentId: null }
      : { mode: 'create-sub', folder: null, parentId: folder.id }
  isFolderEditOpen.value = true
}
</script>
