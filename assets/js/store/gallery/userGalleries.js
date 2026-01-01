import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import userGalleryApi from '../../api/user/gallery.js'

export const useUserGalleriesStore = defineStore('userGalleries', () => {
  const galleries = ref([])
  const isLoading = ref(false)

  async function loadGalleries() {
    isLoading.value = true
    try {
      galleries.value = await userGalleryApi.getGalleries()
    } finally {
      isLoading.value = false
    }
  }

  async function createGallery(title) {
    const gallery = await userGalleryApi.create({ title })
    galleries.value = [gallery, ...galleries.value]
    return gallery
  }

  async function deleteGallery(id) {
    await userGalleryApi.delete(id)
    galleries.value = galleries.value.filter((g) => g.id !== id)
  }

  async function submitGallery(id) {
    const gallery = await userGalleryApi.submit(id)
    const index = galleries.value.findIndex((g) => g.id === id)
    if (index !== -1) {
      galleries.value[index] = gallery
    }
    return gallery
  }

  function updateGallery(gallery) {
    const index = galleries.value.findIndex((g) => g.id === gallery.id)
    if (index !== -1) {
      galleries.value[index] = { ...galleries.value[index], ...gallery }
    }
  }

  function clear() {
    galleries.value = []
  }

  return {
    galleries: readonly(galleries),
    isLoading: readonly(isLoading),
    loadGalleries,
    createGallery,
    deleteGallery,
    submitGallery,
    updateGallery,
    clear
  }
})
