import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import galleryApi from '../../api/gallery/gallery.js'

/**
 * Adapts gallery API response (snake_case) to the format expected by components (camelCase)
 */
function adaptGallery(gallery) {
  return {
    ...gallery,
    imageCount: gallery.image_count,
    publicationDatetime: gallery.publication_datetime,
    coverImage: gallery.cover_image
  }
}

export const useGalleriesStore = defineStore('galleries', () => {
  const galleries = ref([])
  const isLoading = ref(false)
  const isFullyLoaded = ref(false)

  async function loadGalleries({ page = 1, orientation = 'desc' } = {}) {
    // Gallery API has pagination disabled, so only load once
    if (isFullyLoaded.value) {
      return []
    }

    isLoading.value = true
    try {
      const data = await galleryApi.getGalleries({ page, orientation })
      const rawItems = data.member || data
      const items = rawItems.map(adaptGallery)
      galleries.value = [...galleries.value, ...items]
      isFullyLoaded.value = true
      return items
    } finally {
      isLoading.value = false
    }
  }

  function resetGalleries() {
    galleries.value = []
    isFullyLoaded.value = false
  }

  function clear() {
    galleries.value = []
    isFullyLoaded.value = false
  }

  return {
    galleries: readonly(galleries),
    isLoading: readonly(isLoading),
    loadGalleries,
    resetGalleries,
    clear
  }
})
