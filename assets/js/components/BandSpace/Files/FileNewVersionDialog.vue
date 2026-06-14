<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Téléverser une nouvelle version"
    :style="{ width: '32rem' }"
    :closable="!isUploading"
    :close-on-escape="!isUploading"
    @hide="resetForm"
  >
    <div class="flex flex-col gap-4">
      <div
        class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors"
        :class="dropZoneClasses"
        @click="triggerFilePicker"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <input ref="fileInput" type="file" class="hidden" @change="handleFileChange" />

        <template v-if="!selectedFile">
          <i class="pi pi-cloud-upload text-3xl text-surface-400 mb-2"></i>
          <p class="text-sm text-surface-600 dark:text-surface-300">
            Glissez-déposez le fichier ou
            <span class="text-primary-500 underline">cliquez pour parcourir</span>
          </p>
        </template>

        <template v-else>
          <div class="flex items-center justify-center gap-2">
            <i class="pi pi-file text-2xl text-surface-500"></i>
            <div class="text-left min-w-0">
              <p class="text-sm font-medium truncate">{{ selectedFile.name }}</p>
              <p class="text-xs text-surface-400">{{ formatSize(selectedFile.size) }}</p>
            </div>
            <Button
              icon="pi pi-times"
              aria-label="Retirer le fichier"
              text
              rounded
              size="small"
              :disabled="isUploading"
              @click.stop="clearFile"
            />
          </div>
        </template>
      </div>

      <small v-if="fieldErrors.uploadedFile" class="text-red-500">{{ fieldErrors.uploadedFile }}</small>

      <p class="text-xs text-surface-400 italic">
        La nouvelle version remplacera la version actuelle. Les versions précédentes restent
        accessibles.
      </p>

      <div v-if="isUploading" class="flex flex-col gap-1">
        <ProgressBar :value="uploadProgress" />
        <p class="text-xs text-surface-500 text-center">Téléversement… {{ uploadProgress }} %</p>
      </div>

      <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
    </div>

    <template #footer>
      <Button
        label="Annuler"
        severity="secondary"
        text
        :disabled="isUploading"
        @click="visible = false"
      />
      <Button
        label="Téléverser"
        icon="pi pi-cloud-upload"
        :disabled="!selectedFile || isUploading"
        :loading="isUploading"
        @click="handleUpload"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import { computed, reactive, ref } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  fileId: { type: String, default: null }
})

const emit = defineEmits(['uploaded'])

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()

const fileInput = ref(null)
const isDragging = ref(false)
const selectedFile = ref(null)
const isUploading = ref(false)
const uploadProgress = ref(0)
const fieldErrors = reactive({ uploadedFile: null })
const globalError = ref(null)

const dropZoneClasses = computed(() => {
  if (isDragging.value) {
    return 'border-primary-500 bg-primary-50 dark:bg-primary-950'
  }
  return 'border-surface-300 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800'
})

function triggerFilePicker() {
  if (isUploading.value) return
  fileInput.value?.click()
}

function handleFileChange(event) {
  const file = event.target.files?.[0]
  if (file) {
    selectedFile.value = file
    fieldErrors.uploadedFile = null
  }
}

function handleDrop(event) {
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) {
    selectedFile.value = file
    fieldErrors.uploadedFile = null
  }
}

function clearFile() {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

async function handleUpload() {
  if (!selectedFile.value || !props.fileId) {
    fieldErrors.uploadedFile = 'Veuillez sélectionner un fichier'
    return
  }

  isUploading.value = true
  uploadProgress.value = 0
  globalError.value = null
  fieldErrors.uploadedFile = null

  try {
    const result = await filesStore.uploadVersion(
      props.bandSpaceId,
      props.fileId,
      selectedFile.value,
      (percent) => {
        uploadProgress.value = percent
      }
    )

    emit('uploaded', { version: result.version, quotaApproaching: result.quotaApproaching })
    visible.value = false
  } catch (e) {
    if (e.isValidationError) {
      const fileViolation = e.violationsByField?.uploadedFile?.[0]?.message
      if (fileViolation) {
        fieldErrors.uploadedFile = fileViolation
      } else {
        globalError.value = e.message
      }
    } else {
      globalError.value = e.message
    }
  } finally {
    isUploading.value = false
  }
}

function resetForm() {
  selectedFile.value = null
  uploadProgress.value = 0
  globalError.value = null
  fieldErrors.uploadedFile = null
  if (fileInput.value) fileInput.value.value = ''
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} Go`
}
</script>
