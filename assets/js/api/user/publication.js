/** global: Routing */

import axios from 'axios'

export default {
  getPublications({
    page = 1,
    itemsPerPage = 10,
    status = null,
    category = null,
    sortBy = 'creation_datetime',
    sortOrder = 'desc'
  }) {
    const params = new URLSearchParams()
    params.append('page', page.toString())
    params.append('itemsPerPage', itemsPerPage.toString())

    if (status !== null && status !== '') {
      params.append('status', status.toString())
    }
    if (category !== null && category !== '') {
      params.append('category', category.toString())
    }
    if (sortBy) {
      params.append('sortBy', sortBy)
    }
    if (sortOrder) {
      params.append('sortOrder', sortOrder)
    }

    return axios
      .get(Routing.generate('api_user_publications_get_collection') + '?' + params.toString())
      .then((resp) => resp.data)
  },

  delete(id) {
    return axios
      .delete(Routing.generate('api_user_publications_delete', { id }))
      .then((resp) => resp.data)
  },

  create({ title, categoryId }) {
    return axios
      .post(
        Routing.generate('api_user_publications_create'),
        { title, categoryId },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  },

  get(id) {
    return axios
      .get(Routing.generate('api_user_publications_get_edit', { id }))
      .then((resp) => resp.data)
  },

  save(id, { title, shortDescription, categoryId, content }) {
    return axios
      .patch(
        Routing.generate('api_user_publications_patch', { id }),
        { title, shortDescription, categoryId, content },
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
  },

  submit(id) {
    return axios
      .post(
        Routing.generate('api_user_publications_submit', { id }),
        {},
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  },

  uploadImage(id, file, onProgress) {
    const formData = new FormData()
    formData.append('file', file)

    return axios
      .post(Routing.generate('api_user_publications_upload_image', { id }), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          if (onProgress) {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            onProgress(percentCompleted)
          }
        }
      })
      .then((resp) => resp.data)
  },

  uploadCover(id, file, onProgress) {
    const formData = new FormData()
    formData.append('file', file)

    return axios
      .post(Routing.generate('api_user_publications_upload_cover', { id }), formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          if (onProgress) {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            onProgress(percentCompleted)
          }
        }
      })
      .then((resp) => resp.data)
  },

  removeCover(id) {
    return axios
      .delete(Routing.generate('api_user_publications_remove_cover', { id }))
      .then((resp) => resp.data)
  },

  getPreview(id) {
    return axios
      .get(Routing.generate('api_user_publications_preview', { id }))
      .then((resp) => resp.data)
  }
}
