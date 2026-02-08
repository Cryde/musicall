<template>
  <div class="py-6 md:py-10">
    <div v-if="galleryStore.isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <template v-else-if="galleryStore.gallery">
      <div class="flex justify-end mb-4">
        <Breadcrumb :items="breadcrumbItems" />
      </div>

      <div class="flex items-start justify-between gap-4 mb-6">
        <div class="flex flex-col gap-2">
          <h1 class="text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
            {{ galleryStore.gallery.title }}
          </h1>
          <div class="text-sm text-surface-500 dark:text-surface-400">
            Photo de
            <router-link
              v-if="galleryStore.gallery.author?.username && !galleryStore.gallery.author.deletion_datetime"
              :to="{ name: 'app_user_public_profile', params: { username: galleryStore.gallery.author.username } }"
              class="font-semibold text-surface-700 dark:text-surface-200 hover:text-primary transition-colors"
            >{{ galleryAuthorName }}</router-link>
            <span v-else-if="galleryStore.gallery.author" class="font-semibold text-surface-500">{{ galleryAuthorName }}</span>
            <span v-if="galleryStore.gallery.publicationDatetime">
              le {{ formatDate(galleryStore.gallery.publicationDatetime) }}
            </span>
          </div>
        </div>
        <ShareButton :url="shareUrl" :title="shareTitle" />
      </div>

      <MasonryWall
        :items="galleryStore.images"
        :column-width="280"
        :gap="8"
        class="gallery-masonry"
      >
        <template #default="{ item, index }">
          <img
            :src="item.sizes.medium"
            :alt="`${galleryStore.gallery.title} - Photo ${index + 1}`"
            :data-full-image="item.sizes.full"
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
          :alt="`${galleryStore.gallery.title} - Photo ${currentIndex + 1}`"
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
          {{ currentIndex + 1 }} / {{ galleryStore.images.length }}
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import MasonryWall from '@yeger/vue-masonry-wall'
import { format, parseISO } from 'date-fns'
import ProgressSpinner from 'primevue/progressspinner'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ShareButton from '../../components/ShareButton.vue'
import { displayName } from '../../helper/user/displayName.js'
import { useGalleryStore } from '../../store/gallery/gallery.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const route = useRoute()
const galleryStore = useGalleryStore()

const showLightbox = ref(false)
const currentIndex = ref(0)
const currentImage = ref('')
const imageLoading = ref(false)

useTitle(() =>
  galleryStore.gallery ? `${galleryStore.gallery.title} - Photos - MusicAll` : 'Photos - MusicAll'
)

const galleryAuthorName = computed(() =>
  galleryStore.gallery?.author ? displayName(galleryStore.gallery.author) : ''
)

const shareUrl = computed(() => window.location.href)

const shareTitle = computed(() => {
  if (galleryStore.gallery) {
    return `${galleryStore.gallery.title} - Photos - MusicAll`
  }
  return 'Photos - MusicAll'
})

const breadcrumbItems = computed(() => {
  const items = [
    { label: 'Publications', to: { name: 'app_publications' } },
    { label: 'Photos', to: { name: 'app_publications_by_category', params: { slug: 'photos' } } }
  ]
  if (galleryStore.gallery) {
    items.push({ label: galleryStore.gallery.title })
  }
  return items
})

onMounted(async () => {
  const slug = route.params.slug
  await galleryStore.loadGallery(slug)
  trackUmamiEvent('gallery-view', { gallery: slug })
  window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  galleryStore.reset()
  window.removeEventListener('keydown', handleKeydown)
})

function formatDate(dateString) {
  return format(parseISO(dateString), 'dd/MM/yyyy HH:mm')
}

function openLightbox(index) {
  trackUmamiEvent('gallery-image-view')
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
  currentIndex.value = (currentIndex.value + 1) % galleryStore.images.length
  loadCurrentImage()
}

function prevImage() {
  currentIndex.value =
    currentIndex.value === 0 ? galleryStore.images.length - 1 : currentIndex.value - 1
  loadCurrentImage()
}

function loadCurrentImage() {
  imageLoading.value = true
  const image = galleryStore.images[currentIndex.value]
  if (image) {
    const img = new Image()
    img.onload = () => {
      currentImage.value = image.sizes.full
      imageLoading.value = false
    }
    img.src = image.sizes.full
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
</script>

<style>
.gallery-masonry img {
  display: block;
}
</style>
