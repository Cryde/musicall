<template>
  <div class="py-6 md:py-10">
    <div v-if="isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <div v-else-if="error" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
      <i class="pi pi-exclamation-triangle text-4xl text-red-500 mb-4" />
      <p class="text-lg text-surface-700 dark:text-surface-300">{{ error }}</p>
      <Button
        label="Retour a mes galeries"
        icon="pi pi-arrow-left"
        class="mt-4"
        @click="router.push({ name: 'app_user_galleries' })"
      />
    </div>

    <template v-else-if="gallery">
      <!-- Preview Banner -->
      <Message :severity="isAdminReview ? 'info' : 'warn'" :closable="false" class="mb-6">
        <div class="flex items-center justify-between w-full gap-4 flex-wrap">
          <div class="flex items-center gap-2">
            <i :class="isAdminReview ? 'pi pi-shield' : 'pi pi-eye'" />
            <span>
              <strong>{{ isAdminReview ? 'Validation admin' : 'Mode prévisualisation' }}</strong> -
              Cette galerie est en
              <Tag :value="gallery.status_label" :severity="getStatusSeverity(gallery.status)" class="mx-1" />
            </span>
          </div>
          <div class="flex gap-2">
            <!-- Admin review buttons -->
            <template v-if="isAdminReview">
              <Button
                label="Valider"
                icon="pi pi-check"
                size="small"
                severity="success"
                :loading="isProcessing"
                @click="confirmApprove"
              />
              <Button
                label="Rejeter"
                icon="pi pi-times"
                size="small"
                severity="danger"
                :loading="isProcessing"
                @click="confirmReject"
              />
              <Button
                label="Retour"
                icon="pi pi-arrow-left"
                size="small"
                severity="secondary"
                outlined
                @click="router.push({ name: 'admin_galleries_pending' })"
              />
            </template>
            <!-- Regular user buttons -->
            <template v-else>
              <Button
                v-if="gallery.status === STATUS_DRAFT"
                label="Modifier"
                icon="pi pi-pencil"
                size="small"
                severity="secondary"
                @click="router.push({ name: 'app_user_gallery_edit', params: { id: gallery.id } })"
              />
              <Button
                label="Mes galeries"
                icon="pi pi-list"
                size="small"
                severity="secondary"
                outlined
                @click="router.push({ name: 'app_user_galleries' })"
              />
            </template>
          </div>
        </div>
      </Message>

      <ConfirmDialog />

      <!-- Breadcrumb -->
      <div class="flex justify-end mb-4">
        <Breadcrumb :items="breadcrumbItems" />
      </div>

      <!-- Gallery Header -->
      <div class="flex flex-col gap-2 mb-6">
        <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
          {{ gallery.title }}
        </h1>
        <div class="text-sm text-surface-500 dark:text-surface-400">
          Photo de <strong>{{ gallery.author_username }}</strong>
        </div>
        <p v-if="gallery.description" class="text-surface-600 dark:text-surface-300 mt-2">
          {{ gallery.description }}
        </p>
      </div>

      <!-- Images Grid -->
      <div v-if="gallery.images.length === 0" class="text-center py-12 text-surface-500">
        <i class="pi pi-images text-4xl mb-4" />
        <p>Aucune image dans cette galerie</p>
      </div>

      <MasonryWall
        v-else
        :items="gallery.images"
        :column-width="280"
        :gap="8"
        class="gallery-masonry"
      >
        <template #default="{ item, index }">
          <img
            :src="item.medium"
            :alt="`${gallery.title} - Photo ${index + 1}`"
            :data-full-image="item.full"
            :data-index="index"
            class="w-full rounded-lg cursor-pointer transition-opacity duration-200 hover:opacity-80"
            loading="lazy"
            @click="openLightbox(index)"
          />
        </template>
      </MasonryWall>

      <!-- Lightbox -->
      <div
        v-if="showLightbox"
        class="fixed inset-0 z-[99999] bg-black/90 flex items-center justify-center"
        @click.self="closeLightbox"
      >
        <ProgressSpinner v-if="imageLoading" class="absolute" />

        <img
          v-show="!imageLoading"
          :src="currentImage"
          :alt="`${gallery.title} - Photo ${currentIndex + 1}`"
          class="max-h-[90vh] max-w-[90vw] object-contain shadow-2xl"
          @load="imageLoading = false"
        />

        <button
          class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 transition-colors cursor-pointer"
          aria-label="Fermer la galerie"
          @click="closeLightbox"
        >
          <i class="pi pi-times" aria-hidden="true" />
        </button>

        <button
          class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition-colors cursor-pointer"
          aria-label="Image précédente"
          @click="prevImage"
        >
          <i class="pi pi-chevron-left" aria-hidden="true" />
        </button>

        <button
          class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition-colors cursor-pointer"
          aria-label="Image suivante"
          @click="nextImage"
        >
          <i class="pi pi-chevron-right" aria-hidden="true" />
        </button>

        <div class="absolute bottom-4 text-white text-sm">
          {{ currentIndex + 1 }} / {{ gallery.images.length }}
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import MasonryWall from '@yeger/vue-masonry-wall'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import adminGalleryApi from '../../../api/admin/gallery.js'
import userGalleryApi from '../../../api/user/gallery.js'
import { useNotificationStore } from '../../../store/notification/notification.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import Breadcrumb from '../../Global/Breadcrumb.vue'

const STATUS_DRAFT = 1
const STATUS_PENDING = 2

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()

const gallery = ref(null)
const isLoading = ref(true)
const error = ref(null)
const isProcessing = ref(false)

const showLightbox = ref(false)
const currentIndex = ref(0)
const currentImage = ref('')
const imageLoading = ref(false)

const isAdminReview = computed(() => {
  return userSecurityStore.isAdmin && gallery.value?.status === STATUS_PENDING
})

useTitle(() =>
  gallery.value
    ? `Previsualisation: ${gallery.value.title} - MusicAll`
    : 'Previsualisation - MusicAll'
)

const breadcrumbItems = computed(() => {
  const items = [
    { label: 'Publications', to: { name: 'app_publications' } },
    { label: 'Photos', to: { name: 'app_publications_by_category', params: { slug: 'photos' } } }
  ]
  if (gallery.value) {
    items.push({ label: gallery.value.title })
  }
  return items
})

onMounted(async () => {
  await loadPreview()
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown)
})

async function loadPreview() {
  isLoading.value = true
  error.value = null
  try {
    gallery.value = await userGalleryApi.getPreview(route.params.id)
  } catch (e) {
    console.error('Failed to load preview:', e)
    if (e.response?.status === 403) {
      error.value = "Vous n'avez pas acces a cette galerie"
    } else if (e.response?.status === 404) {
      error.value = 'Galerie non trouvee'
    } else {
      error.value = 'Une erreur est survenue lors du chargement de la previsualisation'
    }
  } finally {
    isLoading.value = false
  }
}

function getStatusSeverity(status) {
  switch (status) {
    case STATUS_PENDING:
      return 'warn'
    default:
      return 'secondary'
  }
}

function openLightbox(index) {
  currentIndex.value = index
  loadCurrentImage()
  showLightbox.value = true
}

function closeLightbox() {
  showLightbox.value = false
  currentIndex.value = 0
  currentImage.value = ''
}

function nextImage() {
  currentIndex.value = (currentIndex.value + 1) % gallery.value.images.length
  loadCurrentImage()
}

function prevImage() {
  currentIndex.value =
    currentIndex.value === 0 ? gallery.value.images.length - 1 : currentIndex.value - 1
  loadCurrentImage()
}

function loadCurrentImage() {
  imageLoading.value = true
  const image = gallery.value.images[currentIndex.value]
  if (image) {
    const img = new Image()
    img.onload = () => {
      currentImage.value = image.full
      imageLoading.value = false
    }
    img.src = image.full
  }
}

function handleKeydown(e) {
  if (!showLightbox.value) return

  if (e.code === 'ArrowRight') {
    nextImage()
  } else if (e.code === 'ArrowLeft') {
    prevImage()
  } else if (e.code === 'Escape') {
    closeLightbox()
  }
}

function confirmApprove() {
  confirm.require({
    message: `Valider la galerie "${gallery.value.title}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-check-circle',
    acceptLabel: 'Valider',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-success',
    accept: async () => {
      isProcessing.value = true
      try {
        await adminGalleryApi.approveGallery(gallery.value.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'success',
          summary: 'Galerie validée',
          detail: `La galerie "${gallery.value.title}" a été validée.`,
          life: 3000
        })
        router.push({ name: 'admin_galleries_pending' })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors de la validation.',
          life: 3000
        })
      } finally {
        isProcessing.value = false
      }
    }
  })
}

function confirmReject() {
  confirm.require({
    message: `Rejeter la galerie "${gallery.value.title}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Rejeter',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      isProcessing.value = true
      try {
        await adminGalleryApi.rejectGallery(gallery.value.id)
        await notificationStore.loadNotifications()
        toast.add({
          severity: 'info',
          summary: 'Galerie rejetée',
          detail: `La galerie "${gallery.value.title}" a été rejetée.`,
          life: 3000
        })
        router.push({ name: 'admin_galleries_pending' })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Une erreur est survenue lors du rejet.',
          life: 3000
        })
      } finally {
        isProcessing.value = false
      }
    }
  })
}
</script>

<style>
.gallery-masonry img {
  display: block;
}
</style>
