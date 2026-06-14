<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Téléverser un fichier"
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
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          @change="handleFileChange"
        />

        <template v-if="!selectedFile">
          <i class="pi pi-cloud-upload text-3xl text-surface-400 mb-2"></i>
          <p class="text-sm text-surface-600 dark:text-surface-300">
            Glissez-déposez un fichier ou
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

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Dossier</label>
        <Select
          v-model="form.folderId"
          aria-label="Dossier"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          placeholder="Racine"
          :show-clear="true"
          :disabled="isUploading || activeFolderIsVirtual"
        />
        <small v-if="activeFolderIsVirtual" class="text-surface-400 italic">
          Les dossiers virtuels sont remplis automatiquement par les attachements.
        </small>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Étiquettes</label>
        <MultiSelect
          v-model="form.tagIds"
          aria-label="Étiquettes"
          :options="tags"
          option-label="name"
          option-value="id"
          placeholder="Sélectionner des étiquettes"
          :max-selected-labels="3"
          :disabled="isUploading"
        />

        <div class="flex items-center gap-2 mt-1">
          <InputText
            v-model="newTagName"
            placeholder="Créer une nouvelle étiquette"
            size="small"
            class="flex-1"
            :disabled="isUploading || isCreatingTag"
            @keydown.enter.prevent="handleCreateTag"
          />
          <Button
            icon="pi pi-plus"
            aria-label="Créer l'étiquette"
            size="small"
            severity="secondary"
            :disabled="isUploading || isCreatingTag || !newTagName.trim()"
            :loading="isCreatingTag"
            @click="handleCreateTag"
          />
        </div>
        <small v-if="createTagError" class="text-red-500">{{ createTagError }}</small>
      </div>

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
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import { computed, reactive, ref, watch } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['saved'])

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()

const fileInput = ref(null)
const isDragging = ref(false)
const selectedFile = ref(null)

const form = reactive({
  folderId: null,
  tagIds: []
})

const newTagName = ref('')
const isCreatingTag = ref(false)
const createTagError = ref(null)

const isUploading = ref(false)
const uploadProgress = ref(0)
const fieldErrors = reactive({ uploadedFile: null })
const globalError = ref(null)

const tags = computed(() => filesStore.tags)

const activeFolderId = computed(() => filesStore.activeFolderId)
const activeFolderIsVirtual = computed(
  () => typeof activeFolderId.value === 'string' && activeFolderId.value.startsWith('virtual:')
)

const folderOptions = computed(() => {
  const out = []
  const walk = (nodes, depth) => {
    for (const node of nodes) {
      out.push({ label: '— '.repeat(depth) + node.name, value: node.id })
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children, depth + 1)
      }
    }
  }
  walk(filesStore.folders, 0)
  return out
})

const dropZoneClasses = computed(() => {
  if (isDragging.value) {
    return 'border-primary-500 bg-primary-50 dark:bg-primary-950'
  }
  return 'border-surface-300 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800'
})

watch(visible, (open) => {
  if (open) {
    if (!activeFolderIsVirtual.value && typeof activeFolderId.value === 'string') {
      form.folderId = activeFolderId.value
    } else {
      form.folderId = null
    }
  }
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

async function handleCreateTag() {
  const name = newTagName.value.trim()
  if (!name) return
  isCreatingTag.value = true
  createTagError.value = null
  try {
    const created = await filesStore.createTag(props.bandSpaceId, { name })
    form.tagIds = [...form.tagIds, created.id]
    newTagName.value = ''
  } catch (e) {
    createTagError.value = e.message
  } finally {
    isCreatingTag.value = false
  }
}

async function handleUpload() {
  if (!selectedFile.value) {
    fieldErrors.uploadedFile = 'Veuillez sélectionner un fichier'
    return
  }

  isUploading.value = true
  uploadProgress.value = 0
  fieldErrors.uploadedFile = null
  globalError.value = null

  try {
    const result = await filesStore.uploadFile(
      props.bandSpaceId,
      {
        file: selectedFile.value,
        folderId: form.folderId,
        tagIds: form.tagIds
      },
      (percent) => {
        uploadProgress.value = percent
      }
    )

    emit('saved', { file: result.file, quotaApproaching: result.quotaApproaching })
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
  form.folderId = null
  form.tagIds = []
  newTagName.value = ''
  isCreatingTag.value = false
  createTagError.value = null
  uploadProgress.value = 0
  fieldErrors.uploadedFile = null
  globalError.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} Go`
}
</script>
