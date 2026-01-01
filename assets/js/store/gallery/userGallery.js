import { defineStore } from 'pinia'
import { ref } from 'vue'
import userGalleryApi from '../../api/user/gallery.js'

export const useUserGalleryStore = defineStore('userGallery', () => {
  const gallery = ref(null)
  const images = ref([])
  const isLoading = ref(false)
  const isLoadingImages = ref(false)

  async function loadGallery(id) {
    isLoading.value = true
    try {
      gallery.value = await userGalleryApi.getGallery(id)
    } finally {
      isLoading.value = false
    }
  }

  async function loadImages(id) {
    isLoadingImages.value = true
    try {
      images.value = await userGalleryApi.getImages(id)
    } finally {
      isLoadingImages.value = false
    }
  }

  async function updateGallery({ title, description }) {
    const updated = await userGalleryApi.update(gallery.value.id, { title, description })
    gallery.value = { ...gallery.value, ...updated }
    return updated
  }

  async function uploadImage(file, onProgress) {
    const image = await userGalleryApi.uploadImage(gallery.value.id, file, onProgress)
    images.value = [image, ...images.value]
    return image
  }

  async function deleteImage(imageId) {
    await userGalleryApi.deleteImage(imageId)
    images.value = images.value.filter((i) => i.id !== imageId)
  }

  async function setCover(imageId) {
    const updated = await userGalleryApi.setCover(imageId)
    gallery.value = { ...gallery.value, ...updated }
    return updated
  }

  function reset() {
    gallery.value = null
    images.value = []
    isLoading.value = false
    isLoadingImages.value = false
  }

  return {
    gallery,
    images,
    isLoading,
    isLoadingImages,
    loadGallery,
    loadImages,
    updateGallery,
    uploadImage,
    deleteImage,
    setCover,
    reset
  }
})
