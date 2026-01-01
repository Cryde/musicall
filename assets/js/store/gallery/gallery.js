import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import galleryApi from '../../api/gallery/gallery.js'

export const useGalleryStore = defineStore('gallery', () => {
  const gallery = ref(null)
  const isLoading = ref(false)

  const images = computed(() => gallery.value?.images ?? [])

  async function loadGallery(slug) {
    isLoading.value = true
    try {
      gallery.value = await galleryApi.getGallery(slug)
    } finally {
      isLoading.value = false
    }
  }

  function reset() {
    gallery.value = null
    isLoading.value = false
  }

  return {
    gallery,
    images,
    isLoading,
    loadGallery,
    reset
  }
})
