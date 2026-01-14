<template>
  <div class="py-6 md:py-10">
    <div v-if="userGalleryStore.isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <template v-else-if="userGalleryStore.gallery">
      <div class="flex flex-col gap-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div class="flex items-center gap-4">
            <Button
              icon="pi pi-chevron-left"
              severity="secondary"
              text
              rounded
              @click="router.push({ name: 'app_user_galleries' })"
            />
            <div class="flex flex-col gap-1">
              <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
                {{ userGalleryStore.gallery.title }}
              </h1>
              <p class="text-surface-500 dark:text-surface-400">
                {{ userGalleryStore.images.length }} photo(s)
              </p>
            </div>
          </div>
          <Button label="Parametres" icon="pi pi-cog" severity="secondary" @click="openSettingsModal" />
        </div>

        <!-- Drop zone for upload -->
        <div
          class="border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-xl p-8 text-center cursor-pointer transition-colors hover:border-primary-500 hover:bg-surface-50 dark:hover:bg-surface-800"
          :class="{ 'border-primary-500 bg-primary-50 dark:bg-primary-900/20': isDragging }"
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleDrop"
          @click="triggerFileInput"
        >
          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            @change="handleFileSelect"
          />
          <i class="pi pi-cloud-upload text-4xl text-surface-400 dark:text-surface-500 mb-4" />
          <p class="text-lg font-medium text-surface-700 dark:text-surface-300">
            Glissez-deposez des images ici
          </p>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-2">
            ou cliquez pour selectionner des fichiers
          </p>
        </div>

        <!-- Upload progress -->
        <div v-if="isUploading" class="flex flex-col gap-2">
          <div
            class="flex items-center justify-between p-4 bg-surface-50 dark:bg-surface-800 rounded-lg cursor-pointer"
            @click="isUploadExpanded = !isUploadExpanded"
          >
            <div class="flex items-center gap-3">
              <i class="pi pi-upload text-xl text-primary-500" />
              <div>
                <p class="text-sm font-medium">
                  Upload en cours: {{ uploadedCount }}/{{ totalUploadCount }} image(s)
                </p>
                <ProgressBar :value="overallProgress" :showValue="false" class="h-2 mt-1 w-48" />
              </div>
            </div>
            <Button
              :icon="isUploadExpanded ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
              text
              rounded
              size="small"
            />
          </div>
          <div v-if="isUploadExpanded && uploadingFiles.length > 0" class="flex flex-col gap-2 pl-4">
            <div
              v-for="(file, index) in uploadingFiles"
              :key="index"
              class="flex items-center gap-4 p-3 bg-surface-100 dark:bg-surface-700 rounded-lg"
            >
              <i class="pi pi-image text-lg text-surface-500" />
              <div class="flex-1">
                <p class="text-sm">{{ file.name }}</p>
                <ProgressBar :value="file.progress" :showValue="false" class="h-1.5 mt-1" />
              </div>
              <span class="text-xs text-surface-500">{{ file.progress }}%</span>
            </div>
          </div>
        </div>

        <!-- Images grid -->
        <div v-if="userGalleryStore.isLoadingImages" class="flex justify-center py-12">
          <ProgressSpinner />
        </div>

        <div v-else-if="userGalleryStore.images.length === 0" class="text-center py-12 text-surface-500">
          <i class="pi pi-images text-4xl mb-4" />
          <p>Aucune image dans cette galerie</p>
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
          <div
            v-for="(image, index) in userGalleryStore.images"
            :key="image.id"
            class="relative group aspect-square"
          >
            <img
              :src="image.sizes.medium"
              :alt="`${userGalleryStore.gallery.title} - Photo ${index + 1}`"
              class="w-full h-full object-cover rounded-lg"
              loading="lazy"
            />
            <div
              class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-start justify-between p-2"
            >
              <Button
                v-if="userGalleryStore.gallery.cover_image_id !== image.id"
                v-tooltip.bottom="'Definir comme couverture'"
                icon="pi pi-image"
                severity="info"
                size="small"
                rounded
                @click="handleSetCover(image)"
              />
              <Tag v-else value="Couverture" severity="info" class="text-xs" />

              <Button
                v-if="userGalleryStore.gallery.cover_image_id !== image.id"
                v-tooltip.bottom="'Supprimer'"
                icon="pi pi-trash"
                severity="danger"
                size="small"
                rounded
                @click="handleDeleteImage(image)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Settings Modal -->
      <Dialog v-model:visible="showSettingsModal" modal header="Parametres de la galerie" :style="{ width: '30rem' }">
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-2">
            <label for="title" class="font-medium">Titre</label>
            <InputText id="title" v-model="editTitle" placeholder="Titre de la galerie" />
          </div>
          <div class="flex flex-col gap-2">
            <label for="description" class="font-medium">Description</label>
            <Textarea
              id="description"
              v-model="editDescription"
              rows="4"
              placeholder="Description de la galerie"
            />
          </div>
        </div>
        <template #footer>
          <Button label="Annuler" severity="secondary" text @click="closeSettingsModal" />
          <Button label="Enregistrer" icon="pi pi-check" :loading="isSaving" @click="saveSettings" />
        </template>
      </Dialog>

      <ConfirmDialog />
    </template>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ProgressBar from 'primevue/progressbar'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserGalleriesStore } from '../../../store/gallery/userGalleries.js'
import { useUserGalleryStore } from '../../../store/gallery/userGallery.js'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const userGalleryStore = useUserGalleryStore()
const userGalleriesStore = useUserGalleriesStore()

const fileInput = ref(null)
const isDragging = ref(false)
const uploadingFiles = ref([])
const isUploading = ref(false)
const isUploadExpanded = ref(false)
const uploadedCount = ref(0)
const totalUploadCount = ref(0)

const showSettingsModal = ref(false)
const editTitle = ref('')
const editDescription = ref('')
const isSaving = ref(false)

const overallProgress = computed(() => {
  if (totalUploadCount.value === 0) return 0
  return Math.round((uploadedCount.value / totalUploadCount.value) * 100)
})

useTitle(() =>
  userGalleryStore.gallery
    ? `Modifier: ${userGalleryStore.gallery.title} - MusicAll`
    : 'Modifier la galerie - MusicAll'
)

onMounted(async () => {
  const id = route.params.id
  await userGalleryStore.loadGallery(id)
  await userGalleryStore.loadImages(id)
})

onUnmounted(() => {
  userGalleryStore.reset()
})

watch(
  () => userGalleryStore.gallery,
  (gallery) => {
    if (gallery) {
      editTitle.value = gallery.title
      editDescription.value = gallery.description || ''
    }
  }
)

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(event) {
  const files = Array.from(event.target.files)
  uploadFiles(files)
  event.target.value = ''
}

function handleDrop(event) {
  isDragging.value = false
  const files = Array.from(event.dataTransfer.files).filter((file) =>
    file.type.startsWith('image/')
  )
  uploadFiles(files)
}

function extractErrorMessage(error, fallbackFileName) {
  const data = error?.response?.data
  if (data?.violations?.length > 0) {
    return data.violations[0].message
  }
  if (data?.detail) {
    return data.detail
  }
  return `Impossible d'ajouter ${fallbackFileName}`
}

async function uploadFiles(files) {
  if (files.length === 0) return

  isUploading.value = true
  uploadedCount.value = 0
  totalUploadCount.value = files.length

  for (const file of files) {
    const uploadFile = { name: file.name, progress: 0 }
    uploadingFiles.value.push(uploadFile)

    try {
      await userGalleryStore.uploadImage(file, (progress) => {
        uploadFile.progress = progress
      })
      toast.add({
        severity: 'success',
        summary: 'Succes',
        detail: `${file.name} ajoute`,
        life: 2000
      })
    } catch (error) {
      toast.add({
        severity: 'error',
        summary: 'Erreur',
        detail: extractErrorMessage(error, file.name),
        life: 5000
      })
    }

    uploadedCount.value++
    uploadingFiles.value = uploadingFiles.value.filter((f) => f !== uploadFile)
  }

  // Reset all state when uploads are complete
  isUploading.value = false
  totalUploadCount.value = 0
  uploadedCount.value = 0
}

async function handleSetCover(image) {
  try {
    await userGalleryStore.setCover(image.id)
    userGalleriesStore.updateGallery({
      id: userGalleryStore.gallery.id,
      cover_image_url: image.sizes.medium
    })
    toast.add({
      severity: 'success',
      summary: 'Succes',
      detail: 'Image de couverture modifiee',
      life: 3000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de modifier la couverture',
      life: 3000
    })
  }
}

function handleDeleteImage(image) {
  confirm.require({
    message: 'Etes-vous sur de vouloir supprimer cette image ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await userGalleryStore.deleteImage(image.id)
        toast.add({
          severity: 'success',
          summary: 'Succes',
          detail: 'Image supprimee',
          life: 3000
        })
      } catch {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: "Impossible de supprimer l'image",
          life: 3000
        })
      }
    }
  })
}

function openSettingsModal() {
  editTitle.value = userGalleryStore.gallery.title
  editDescription.value = userGalleryStore.gallery.description || ''
  showSettingsModal.value = true
}

function closeSettingsModal() {
  showSettingsModal.value = false
}

async function saveSettings() {
  if (!editTitle.value.trim()) {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Le titre est requis',
      life: 3000
    })
    return
  }

  isSaving.value = true
  try {
    await userGalleryStore.updateGallery({
      title: editTitle.value,
      description: editDescription.value
    })
    userGalleriesStore.updateGallery({
      id: userGalleryStore.gallery.id,
      title: editTitle.value,
      description: editDescription.value
    })
    closeSettingsModal()
    toast.add({
      severity: 'success',
      summary: 'Succes',
      detail: 'Parametres enregistres',
      life: 3000
    })
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: "Impossible d'enregistrer les parametres",
      life: 3000
    })
  } finally {
    isSaving.value = false
  }
}
</script>
