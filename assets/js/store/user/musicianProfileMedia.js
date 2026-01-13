import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import musicianProfileMediaApi from '../../api/user/musicianProfileMedia.js'

export const useMusicianProfileMediaStore = defineStore('musicianProfileMedia', () => {
  const media = ref([])
  const isLoading = ref(false)
  const isAdding = ref(false)
  const isDeleting = ref(false)

  async function loadMedia() {
    isLoading.value = true
    try {
      const data = await musicianProfileMediaApi.getMedia()
      media.value = data.member || []
      return media.value
    } finally {
      isLoading.value = false
    }
  }

  async function addMedia(url, title = null) {
    isAdding.value = true
    try {
      const newMedia = await musicianProfileMediaApi.addMedia({ url, title })
      media.value.push(newMedia)
      return newMedia
    } finally {
      isAdding.value = false
    }
  }

  async function deleteMedia(id) {
    isDeleting.value = true
    try {
      await musicianProfileMediaApi.deleteMedia(id)
      media.value = media.value.filter((m) => m.id !== id)
    } finally {
      isDeleting.value = false
    }
  }

  function clear() {
    media.value = []
  }

  return {
    media: readonly(media),
    isLoading: readonly(isLoading),
    isAdding: readonly(isAdding),
    isDeleting: readonly(isDeleting),
    loadMedia,
    addMedia,
    deleteMedia,
    clear
  }
})
