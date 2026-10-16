<template>
  <div
    v-if="selectedFiles.length > 0"
    class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 bg-surface-900 dark:bg-surface-800 text-white rounded-xl shadow-xl px-4 py-2 flex items-center gap-3"
    role="region"
    :aria-label="t('band_space_files.bulk_bar.region_label')"
  >
    <span class="text-sm font-medium">
      {{ t('band_space_files.bulk_bar.selected_count', selectedFiles.length) }}
    </span>

    <div class="w-px h-6 bg-surface-600"></div>

    <template v-if="isTrash">
      <!-- The tooltip sits on the wrapper, since a disabled button fires no pointer event of its
           own, and a tooltip carries no accessible name either, hence the sr-only twin. -->
      <span v-tooltip.top="restoreBlockedReason">
        <Button
          :label="t('band_space_files.common.restore')"
          icon="pi pi-undo"
          size="small"
          severity="secondary"
          :loading="busy === 'restore'"
          :disabled="!!busy || restoreBlockedReason !== null"
          @click="handleRestore"
        />
        <span v-if="restoreBlockedReason" class="sr-only">{{ restoreBlockedReason }}</span>
      </span>
    </template>

    <template v-else>
      <Button
        :label="t('band_space_files.bulk_bar.move')"
        icon="pi pi-arrows-h"
        size="small"
        severity="secondary"
        :disabled="!!busy"
        @click="(event) => movePopover.toggle(event)"
      />
      <Popover ref="movePopover">
        <div class="flex flex-col gap-2 min-w-[18rem]">
          <Select
            v-model="folderDraft"
            :aria-label="t('band_space_files.bulk_bar.move_target')"
            :options="folderOptions"
            option-label="label"
            option-value="value"
            option-disabled="disabled"
            :placeholder="t('band_space_files.folder.root')"
          />
          <small v-if="rootRefusal" class="text-xs text-surface-300">{{ rootRefusal }}</small>
          <Button :label="t('band_space_files.bulk_bar.move')" size="small" :loading="busy === 'move'" @click="handleMove" />
        </div>
      </Popover>

      <span v-tooltip.top="deleteBlocked">
        <Button
          :label="t('band_space_files.common.delete')"
          icon="pi pi-trash"
          size="small"
          severity="danger"
          :loading="busy === 'delete'"
          :disabled="!!busy || deleteBlocked !== null"
          @click="confirmDelete"
        />
        <span v-if="deleteBlocked" class="sr-only">{{ deleteBlocked }}</span>
      </span>
    </template>

    <div class="w-px h-6 bg-surface-600"></div>

    <Button
      icon="pi pi-times"
      size="small"
      text
      severity="secondary"
      class="!text-white"
      v-tooltip.top="t('band_space_files.bulk_bar.clear_selection')"
      :aria-label="t('band_space_files.bulk_bar.clear_selection')"
      @click="filesStore.clearSelection"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiErrorDetail } from '../../../api/utils/apiErrorDetail.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { deleteBlockedReason, restoreOwnershipReason } from '../../../utils/fileActions.js'
import { canFileSitAtRoot, folderSelectOptions } from '../../../utils/fileListing.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** The trash lists different rows and offers only Restaurer. */
  isTrash: { type: Boolean, default: false }
})

const { t } = useI18n()
const filesStore = useBandFilesStore()
const userSecurityStore = useUserSecurityStore()
const { isAdmin } = useBandSpaceNavigation()
const toast = useToast()
const confirm = useConfirm()

const busy = ref(null)
const folderDraft = ref(null)
const movePopover = ref()

// Selection only happens on rows the list holds, so every ticked id is one of the loaded files.
const selectedFiles = computed(() =>
  filesStore.files.filter((file) => filesStore.selectedFileIds.has(file.id))
)

const currentUserId = computed(() => userSecurityStore.userProfile?.id ?? null)

/**
 * The batch is one transaction, so a single file in the way takes the whole selection down. Rather
 * than let somebody run it and read a refusal, the button is greyed out and says which files it is
 * waiting on: with twelve rows ticked, "un fichier est attaché" is not something a member can act on.
 */
const deleteBlocked = computed(() =>
  deleteBlockedReason(selectedFiles.value, currentUserId.value, isAdmin.value)
)

const restoreBlockedReason = computed(() =>
  restoreOwnershipReason(selectedFiles.value, currentUserId.value, isAdmin.value)
)

/**
 * The root lists only the files nothing points at, so it is refused as soon as one selected file
 * carries an attachment. Same rule as the single file move dialog, read across the selection.
 */
const rootRefusal = computed(() => {
  const attached = selectedFiles.value.filter((file) => !canFileSitAtRoot(file))
  if (attached.length === 0) {
    return null
  }

  return t('band_space_files.bulk_bar.root_refused', attached.length)
})

const folderOptions = computed(() =>
  folderSelectOptions(filesStore.folders, rootRefusal.value !== null)
)

async function runBulk(name, fn) {
  busy.value = name
  try {
    await fn()
  } catch (e) {
    // The refusal names the files that blocked the batch, which is the only part worth reading.
    toast.add({
      severity: 'error',
      summary: t('band_space_files.bulk_bar.failed'),
      detail: apiErrorDetail(e, t('band_space_files.upload.generic_error')),
      life: 8000
    })
  } finally {
    busy.value = null
  }
}

function handleMove() {
  movePopover.value?.hide()
  runBulk('move', async () => {
    await filesStore.bulkMoveFiles(props.bandSpaceId, folderDraft.value ?? null)
    toast.add({ severity: 'success', summary: t('band_space_files.bulk_bar.moved'), life: 3000 })
    folderDraft.value = null
  })
}

function handleRestore() {
  runBulk('restore', async () => {
    await filesStore.bulkRestoreFiles(props.bandSpaceId)
    toast.add({ severity: 'success', summary: t('band_space_files.bulk_bar.restored'), life: 3000 })
  })
}

function confirmDelete() {
  const count = selectedFiles.value.length
  confirm.require({
    message: t(
      'band_space_files.bulk_bar.delete_confirm_message',
      { days: filesStore.trashRetentionDays },
      count
    ),
    header: t('band_space_files.bulk_bar.delete_confirm_header'),
    icon: 'pi pi-trash',
    acceptLabel: t('band_space_files.common.delete'),
    rejectLabel: t('band_space_files.common.cancel'),
    acceptClass: 'p-button-danger',
    accept: () => {
      runBulk('delete', async () => {
        await filesStore.bulkDeleteFiles(props.bandSpaceId)
        toast.add({
          severity: 'success',
          summary: t('band_space_files.bulk_bar.deleted'),
          life: 3000
        })
      })
    }
  })
}
</script>
