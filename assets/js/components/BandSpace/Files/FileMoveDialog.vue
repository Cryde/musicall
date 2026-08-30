<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Déplacer le fichier"
    :style="{ width: '28rem' }"
    @hide="resetState"
  >
    <div class="flex flex-col gap-4">
      <p v-if="file" class="text-sm text-surface-500">
        <i :class="iconForMime(file.mime_type)" class="mr-2"></i>
        <span class="font-medium text-surface-700 dark:text-surface-200">{{ file.original_name }}</span>
      </p>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Dossier de destination</label>
        <Select
          v-model="targetFolderId"
          aria-label="Dossier de destination"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          option-disabled="disabled"
          placeholder="Racine"
          :disabled="isSubmitting"
        />
        <small v-if="rootRefusal" class="text-xs text-surface-500 dark:text-surface-400">
          {{ rootRefusal }}
        </small>
      </div>

      <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
    </div>

    <template #footer>
      <Button
        label="Annuler"
        severity="secondary"
        text
        :disabled="isSubmitting"
        @click="visible = false"
      />
      <Button
        label="Déplacer"
        :loading="isSubmitting"
        :disabled="isSubmitting || !hasChanged"
        @click="handleConfirm"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { folderSelectOptions, rootDestinationRefusal } from '../../../utils/fileListing.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  file: { type: Object, default: null }
})

const emit = defineEmits(['moved'])

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()

const targetFolderId = ref(null)
const isSubmitting = ref(false)
const globalError = ref(null)

/**
 * « Racine » is a destination like any other, and a refused one for a file with attachments: see
 * canFileSitAtRoot(). It used to be reachable only by clearing the field, which is how the refusal
 * came to be unsayable.
 */
const rootRefusal = computed(() => rootDestinationRefusal(props.file, filesStore.virtualFolders))

const folderOptions = computed(() =>
  folderSelectOptions(filesStore.folders, rootRefusal.value !== null)
)

const hasChanged = computed(() => {
  const current = props.file?.folder_id ?? null
  return (targetFolderId.value ?? null) !== (current ?? null)
})

watch(visible, (open) => {
  if (open) {
    targetFolderId.value = props.file?.folder_id ?? null
    globalError.value = null
  }
})

async function handleConfirm() {
  if (!props.file) return
  // The disabled option is what stops this in the UI; this is what stops it if the field is ever
  // swapped for another control. The root taking an attached file out of the tree is the whole bug.
  if (targetFolderId.value === null && rootRefusal.value !== null) {
    globalError.value = rootRefusal.value

    return
  }
  isSubmitting.value = true
  globalError.value = null
  try {
    await bandSpaceFilesApi.updateFile(props.bandSpaceId, props.file.id, {
      folder_id: targetFolderId.value ?? null
    })
    filesStore.applyFileMoved(props.bandSpaceId, props.file.id, targetFolderId.value ?? null)
    emit('moved', { fileId: props.file.id, folderId: targetFolderId.value ?? null })
    visible.value = false
  } catch (e) {
    globalError.value = e.message
  } finally {
    isSubmitting.value = false
  }
}

function resetState() {
  targetFolderId.value = null
  isSubmitting.value = false
  globalError.value = null
}

function iconForMime(mime) {
  if (!mime) return 'pi pi-file'
  if (mime.startsWith('audio/')) return 'pi pi-volume-up'
  if (mime.startsWith('image/')) return 'pi pi-image'
  if (mime.startsWith('video/')) return 'pi pi-video'
  if (mime === 'application/pdf') return 'pi pi-file-pdf'
  return 'pi pi-file'
}
</script>
