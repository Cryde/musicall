<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Paramètres de la publication"
    :style="{ width: '600px' }"
    :closable="!isSaving"
    :closeOnEscape="!isSaving"
    @show="initForm"
    @hide="handleClose"
  >
    <div class="flex flex-col gap-6">
      <!-- Title -->
      <div class="flex flex-col gap-2">
        <label for="settings-title" class="font-medium text-surface-700 dark:text-surface-200">
          Titre
        </label>
        <InputText
          id="settings-title"
          v-model="title"
          placeholder="Le titre de votre publication"
          class="w-full"
          :disabled="isSaving"
        />
      </div>

      <!-- Category -->
      <div class="flex flex-col gap-2">
        <label for="settings-category" class="font-medium text-surface-700 dark:text-surface-200">
          Catégorie
        </label>
        <Select
          id="settings-category"
          v-model="selectedCategory"
          :options="publicationsStore.publicationCategories"
          optionLabel="title"
          optionValue="id"
          placeholder="Choisir une catégorie"
          class="w-full"
          :disabled="isSaving"
        />
      </div>

      <!-- Description -->
      <div class="flex flex-col gap-2">
        <label for="settings-description" class="font-medium text-surface-700 dark:text-surface-200">
          Description courte
        </label>
        <Textarea
          id="settings-description"
          v-model="shortDescription"
          placeholder="Cette description apparaîtra sur la page d'accueil"
          rows="3"
          class="w-full"
          :disabled="isSaving"
        />
        <small class="text-surface-500">Cette courte description apparaîtra sur la page d'accueil</small>
      </div>

      <!-- Cover Image -->
      <div class="flex flex-col gap-2">
        <label class="font-medium text-surface-700 dark:text-surface-200">
          Image de couverture
        </label>

        <div v-if="coverUrl" class="relative inline-block">
          <img
            :src="coverUrl"
            alt="Cover"
            class="max-w-xs rounded-lg border border-surface-200 dark:border-surface-700"
          />
          <Button
            icon="pi pi-times"
            severity="danger"
            size="small"
            rounded
            class="absolute -top-2 -right-2"
            :disabled="isSaving"
            @click="handleRemoveCover"
          />
        </div>

        <div v-else class="p-4 border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-lg text-center">
          <i class="pi pi-image text-3xl text-surface-400 mb-2" />
          <p class="text-surface-500 dark:text-surface-400 text-sm">
            Aucune image de couverture
          </p>
        </div>

        <FileUpload
          ref="fileUploadRef"
          mode="basic"
          accept="image/*"
          :maxFileSize="4000000"
          :chooseLabel="coverUrl ? 'Remplacer l\'image' : 'Choisir une image'"
          :auto="true"
          :disabled="isSaving || publicationEditStore.isUploading"
          customUpload
          @uploader="handleCoverUpload"
        />

        <ProgressBar
          v-if="publicationEditStore.isUploading"
          :value="publicationEditStore.uploadProgress"
          :showValue="true"
          class="mt-2"
        />

        <small class="text-surface-500">
          Taille maximale : 4 Mo. Dimensions maximales : 4000x4000 pixels
        </small>
      </div>

      <!-- Error messages -->
      <Message v-if="errors.length > 0" severity="error" :closable="false">
        <ul class="list-disc list-inside">
          <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
        </ul>
      </Message>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Annuler"
          severity="secondary"
          text
          :disabled="isSaving"
          @click="handleClose"
        />
        <Button
          label="Enregistrer"
          icon="pi pi-check"
          :loading="isSaving"
          :disabled="!canSave"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, ref } from 'vue'
import { usePublicationEditStore } from '../../store/publication/publicationEdit.js'
import { usePublicationsStore } from '../../store/publication/publications.js'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const publicationEditStore = usePublicationEditStore()
const publicationsStore = usePublicationsStore()

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const title = ref('')
const shortDescription = ref('')
const selectedCategory = ref(null)
const coverUrl = ref(null)
const isSaving = ref(false)
const errors = ref([])
const fileUploadRef = ref(null)

const canSave = computed(() => {
  return title.value.trim().length >= 3 && !isSaving.value
})

async function initForm() {
  if (publicationsStore.publicationCategories.length === 0) {
    await publicationsStore.loadCategories()
  }
  if (publicationEditStore.publication) {
    title.value = publicationEditStore.publication.title || ''
    shortDescription.value = publicationEditStore.publication.short_description || ''
    selectedCategory.value = publicationEditStore.publication.category?.id || null
    coverUrl.value = publicationEditStore.publication.cover_url || null
  }
  errors.value = []
}

async function handleCoverUpload(event) {
  const file = event.files?.[0]
  if (!file) return

  const uri = await publicationEditStore.uploadCover(file)
  if (uri) {
    coverUrl.value = uri
  } else {
    errors.value = publicationEditStore.errors
  }

  // Clear the file input for next upload
  if (fileUploadRef.value) {
    fileUploadRef.value.clear()
  }
}

async function handleRemoveCover() {
  const success = await publicationEditStore.removeCover()
  if (success) {
    coverUrl.value = null
  } else {
    errors.value = publicationEditStore.errors
  }
}

async function handleSave() {
  if (!canSave.value) return

  isSaving.value = true
  errors.value = []

  const success = await publicationEditStore.save({
    title: title.value.trim(),
    shortDescription: shortDescription.value.trim(),
    categoryId: selectedCategory.value,
    content: publicationEditStore.publication.content || '',
  })

  if (success) {
    emit('saved')
    visible.value = false
  } else {
    errors.value = publicationEditStore.errors
  }

  isSaving.value = false
}

function handleClose() {
  if (!isSaving.value) {
    visible.value = false
  }
}
</script>
