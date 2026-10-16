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
            <i18n-t keypath="publication.gallery.author">
              <template #author>
                <UserName
                  v-if="galleryStore.gallery.author?.username && !galleryStore.gallery.author.deletion_datetime"
                  :username="galleryStore.gallery.author.username"
                  :display-name="galleryAuthorName"
                  :to="{ name: 'app_user_public_profile', params: { username: galleryStore.gallery.author.username } }"
                  class="font-semibold text-surface-700 dark:text-surface-200 hover:text-primary transition-colors"
                />
                <span v-else-if="galleryStore.gallery.author" class="font-semibold text-surface-500">{{ galleryAuthorName }}</span>
              </template>
            </i18n-t>
            <span v-if="galleryStore.gallery.publicationDatetime">
              {{ t('publication.gallery.show.published_on', { date: formatDate(galleryStore.gallery.publicationDatetime) }) }}
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
            :alt="t('publication.gallery.photo_alt', { title: galleryStore.gallery.title, number: index + 1 })"
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
        ref="lightboxRef"
        tabindex="-1"
        role="dialog"
        aria-modal="true"
        :aria-label="galleryStore.gallery?.title ? t('publication.gallery.lightbox.label', { title: galleryStore.gallery.title }) : t('publication.gallery.lightbox.label_empty')"
        class="fixed inset-0 z-[99999] bg-black/90 flex items-center justify-center outline-none"
        @click.self="closeLightbox"
      >
        <ProgressSpinner v-if="imageLoading" class="absolute" />

        <img
          v-show="!imageLoading"
          :src="currentImage"
          :alt="t('publication.gallery.photo_alt', { title: galleryStore.gallery.title, number: currentIndex + 1 })"
          class="max-h-[90vh] max-w-[90vw] object-contain shadow-2xl"
          @load="imageLoading = false"
        />

        <button
          class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 transition-colors cursor-pointer"
          :aria-label="t('publication.gallery.lightbox.close')"
          @click="closeLightbox"
        >
          <i class="pi pi-times" aria-hidden="true" />
        </button>

        <button
          class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition-colors cursor-pointer"
          :aria-label="t('publication.gallery.lightbox.previous')"
          @click="prevImage"
        >
          <i class="pi pi-chevron-left" aria-hidden="true" />
        </button>

        <button
          class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-gray-300 transition-colors cursor-pointer"
          :aria-label="t('publication.gallery.lightbox.next')"
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
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useTitle } from '@vueuse/core'
import { MasonryWall } from '@yeger/vue-masonry-wall'
import { format, parseISO } from 'date-fns'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ShareButton from '../../components/ShareButton.vue'
import UserName from '../../components/User/UserName.vue'
import { displayName } from '../../helper/user/displayName.js'
import { useGalleryStore } from '../../store/gallery/gallery.js'
import Breadcrumb from '../Global/Breadcrumb.vue'

const { t } = useI18n()
const route = useRoute()
const galleryStore = useGalleryStore()

const showLightbox = ref(false)
const lightboxRef = ref(null)
// Element to restore focus to when the lightbox closes (WCAG 2.4.3).
let lightboxTrigger = null
const currentIndex = ref(0)
const currentImage = ref('')
const imageLoading = ref(false)

const galleryAuthorName = computed(() =>
  galleryStore.gallery?.author ? displayName(galleryStore.gallery.author) : ''
)

const shareUrl = computed(() => window.location.href)

const shareTitle = computed(() => {
  if (galleryStore.gallery) {
    return t('publication.gallery.show.page_title', { title: galleryStore.gallery.title })
  }
  return t('publication.gallery.show.page_title_empty')
})

useTitle(shareTitle)

const breadcrumbItems = computed(() => {
  const items = [
    { label: t('publication.common.publications'), to: { name: 'app_publications' } },
    {
      label: t('publication.gallery.photos'),
      to: { name: 'app_publications_by_category', params: { slug: 'photos' } }
    }
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
  lightboxTrigger = document.activeElement
  currentIndex.value = index
  loadCurrentImage()
  showLightbox.value = true
  nextTick(() => lightboxRef.value?.focus())
}

function closeLightbox() {
  showLightbox.value = false
  currentIndex.value = 0
  currentImage.value = ''
  lightboxTrigger?.focus?.()
  lightboxTrigger = null
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
