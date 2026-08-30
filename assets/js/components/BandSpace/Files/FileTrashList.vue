<template>
  <div>
    <div v-if="isLoading && files.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 3" :key="i" width="100%" height="3.5rem" borderRadius="0.5rem" />
    </div>

    <div
      v-else-if="files.length === 0"
      class="flex flex-col items-center justify-center py-16 text-center text-surface-400"
    >
      <i class="pi pi-trash text-5xl mb-4" aria-hidden="true"></i>
      <p class="text-sm italic">La corbeille est vide.</p>
    </div>

    <div v-else class="flex flex-col gap-2">
      <p class="text-xs text-surface-500 dark:text-surface-400 px-1">
        Les fichiers supprimés restent ici {{ filesStore.trashRetentionDays }} jours, puis sont
        définitivement effacés. Vous pouvez les restaurer avant cette date.
      </p>

      <div class="flex items-center gap-2 px-1">
        <Checkbox
          :model-value="allSelected"
          binary
          size="small"
          aria-label="Tout sélectionner"
          @update:model-value="toggleAll"
        />
        <span class="text-xs text-surface-500 dark:text-surface-400">Tout sélectionner</span>
      </div>

      <div
        v-for="file in files"
        :key="file.id"
        class="flex items-center gap-3 p-3 rounded-lg border"
        :class="
          isSelected(file)
            ? 'bg-primary-50 dark:bg-primary-900/30 border-primary-200 dark:border-primary-700/50'
            : 'bg-surface-0 dark:bg-surface-900 border-surface-200 dark:border-surface-700'
        "
      >
        <Checkbox
          :model-value="isSelected(file)"
          binary
          size="small"
          class="shrink-0"
          :aria-label="`Sélectionner ${file.original_name}`"
          @mousedown="rememberModifiers"
          @keydown="rememberModifiers"
          @update:model-value="() => toggleFromCheckbox(file)"
        />
        <i class="pi pi-file text-lg text-surface-500 shrink-0" aria-hidden="true"></i>

        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-surface-800 dark:text-surface-100 truncate">
            {{ file.original_name }}
          </p>
          <span class="text-xs text-surface-500 dark:text-surface-400">
            {{ formatBytes(file.size) }} · supprimé le {{ formatDateLong(file.archive_datetime) }} ·
            <span :class="remainingClass(file)">{{ remainingLabel(file) }}</span>
          </span>
        </div>

        <Button
          label="Restaurer"
          icon="pi pi-replay"
          text
          size="small"
          :disabled="!canRestore(file)"
          :loading="busyId === file.id"
          v-tooltip.top="canRestore(file) ? null : 'Seul le créateur ou un administrateur peut restaurer ce fichier'"
          @click="handleRestore(file)"
        />
        <Button
          v-if="isAdmin"
          icon="pi pi-trash"
          severity="danger"
          text
          size="small"
          aria-label="Supprimer définitivement"
          v-tooltip.top="'Supprimer définitivement'"
          :loading="busyId === file.id"
          @click="handlePermanentDelete(file)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { isFileCreatorOrAdmin } from '../../../utils/bandSpaceFilePermissions.js'
import { formatDateLong } from '../../../utils/date.js'
import { areAllSelected, selectionAfterClick } from '../../../utils/fileSelection.js'
import { formatBytes } from '../../../utils/formatBytes.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  files: { type: Array, required: true },
  isLoading: { type: Boolean, default: false },
  isAdmin: { type: Boolean, default: false }
})

const filesStore = useBandFilesStore()
const userSecurityStore = useUserSecurityStore()
const confirm = useConfirm()
const toast = useToast()

const busyId = ref(null)

const orderedIds = computed(() => props.files.map((file) => file.id))
const allSelected = computed(() =>
  areAllSelected(orderedIds.value, [...filesStore.selectedFileIds])
)

function isSelected(file) {
  return filesStore.selectedFileIds.has(file.id)
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
  filesStore.setSelection(checked ? orderedIds.value : [], null)
}

function canRestore(file) {
  return isFileCreatorOrAdmin(file, userSecurityStore.userProfile?.id, props.isAdmin)
}

function daysRemaining(file) {
  if (!file.purge_datetime) {
    return null
  }

  const millisecondsPerDay = 86400000

  return Math.ceil((new Date(file.purge_datetime).getTime() - Date.now()) / millisecondsPerDay)
}

function remainingLabel(file) {
  const days = daysRemaining(file)
  if (days === null) {
    return ''
  }
  if (days <= 0) {
    return 'suppression imminente'
  }

  return days === 1 ? "plus qu'un jour" : `encore ${days} jours`
}

function remainingClass(file) {
  const days = daysRemaining(file)

  return days !== null && days <= 7 ? 'text-amber-600 dark:text-amber-400 font-medium' : ''
}

async function handleRestore(file) {
  busyId.value = file.id
  try {
    await filesStore.restoreFile(props.bandSpaceId, file.id)
    toast.add({ severity: 'success', summary: 'Fichier restauré', life: 3000 })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: error.message || 'Impossible de restaurer le fichier',
      life: 5000
    })
  } finally {
    busyId.value = null
  }
}

function handlePermanentDelete(file) {
  confirm.require({
    header: 'Supprimer définitivement',
    message: `« ${file.original_name} » sera effacé immédiatement et cette action est irréversible. Continuer ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer définitivement',
    acceptClass: 'p-button-danger',
    accept: async () => {
      busyId.value = file.id
      try {
        await filesStore.permanentDeleteFile(props.bandSpaceId, file.id)
        toast.add({ severity: 'success', summary: 'Fichier supprimé définitivement', life: 3000 })
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: error.message || 'Impossible de supprimer le fichier',
          life: 5000
        })
      } finally {
        busyId.value = null
      }
    }
  })
}
</script>
