import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import publicationApi from '../../api/user/publication.js'

export const usePublicationEditStore = defineStore('publicationEdit', () => {
  const publication = ref(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const isSubmitting = ref(false)
  const isUploading = ref(false)
  const uploadProgress = ref(0)
  const errors = ref([])

  async function loadPublication(id) {
    isLoading.value = true
    errors.value = []
    try {
      publication.value = await publicationApi.get(id)
    } catch (e) {
      console.error('Failed to load publication:', e)
      errors.value = extractErrors(e)
      publication.value = null
    } finally {
      isLoading.value = false
    }
  }

  async function createPublication({ title, categoryId }) {
    isLoading.value = true
    errors.value = []
    try {
      const result = await publicationApi.create({ title, categoryId })
      publication.value = result
      return result
    } catch (e) {
      console.error('Failed to create publication:', e)
      errors.value = extractErrors(e)
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function save({ title, shortDescription, categoryId, content }) {
    if (!publication.value) return false

    isSaving.value = true
    errors.value = []
    try {
      const result = await publicationApi.save(publication.value.id, {
        title,
        shortDescription,
        categoryId,
        content,
      })
      publication.value = result
      return true
    } catch (e) {
      console.error('Failed to save publication:', e)
      errors.value = extractErrors(e)
      return false
    } finally {
      isSaving.value = false
    }
  }

  async function submit() {
    if (!publication.value) return false

    isSubmitting.value = true
    errors.value = []
    try {
      const result = await publicationApi.submit(publication.value.id)
      publication.value = result
      return true
    } catch (e) {
      console.error('Failed to submit publication:', e)
      errors.value = extractErrors(e)
      return false
    } finally {
      isSubmitting.value = false
    }
  }

  async function uploadImage(file) {
    if (!publication.value) return null

    isUploading.value = true
    uploadProgress.value = 0
    errors.value = []
    try {
      const result = await publicationApi.uploadImage(publication.value.id, file, (progress) => {
        uploadProgress.value = progress
      })
      return result.uri
    } catch (e) {
      console.error('Failed to upload image:', e)
      errors.value = extractErrors(e)
      return null
    } finally {
      isUploading.value = false
      uploadProgress.value = 0
    }
  }

  async function uploadCover(file) {
    if (!publication.value) return null

    isUploading.value = true
    uploadProgress.value = 0
    errors.value = []
    try {
      const result = await publicationApi.uploadCover(publication.value.id, file, (progress) => {
        uploadProgress.value = progress
      })
      publication.value.cover_url = result.uri
      return result.uri
    } catch (e) {
      console.error('Failed to upload cover:', e)
      errors.value = extractErrors(e)
      return null
    } finally {
      isUploading.value = false
      uploadProgress.value = 0
    }
  }

  async function removeCover() {
    if (!publication.value) return false

    isSaving.value = true
    errors.value = []
    try {
      await publicationApi.removeCover(publication.value.id)
      publication.value.cover_url = null
      return true
    } catch (e) {
      console.error('Failed to remove cover:', e)
      errors.value = extractErrors(e)
      return false
    } finally {
      isSaving.value = false
    }
  }

  function updateContent(content) {
    if (publication.value) {
      publication.value.content = content
    }
  }

  function clear() {
    publication.value = null
    isLoading.value = false
    isSaving.value = false
    isSubmitting.value = false
    isUploading.value = false
    uploadProgress.value = 0
    errors.value = []
  }

  function extractErrors(error) {
    if (error.response?.data?.violations) {
      return error.response.data.violations.map((v) => v.message)
    }
    if (error.response?.data?.detail) {
      return [error.response.data.detail]
    }
    if (error.response?.data?.message) {
      return [error.response.data.message]
    }
    return ['Une erreur est survenue']
  }

  return {
    publication: readonly(publication),
    isLoading: readonly(isLoading),
    isSaving: readonly(isSaving),
    isSubmitting: readonly(isSubmitting),
    isUploading: readonly(isUploading),
    uploadProgress: readonly(uploadProgress),
    errors: readonly(errors),
    loadPublication,
    createPublication,
    save,
    submit,
    uploadImage,
    uploadCover,
    removeCover,
    updateContent,
    clear,
  }
})
