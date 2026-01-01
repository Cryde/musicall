/** global: Routing */

import axios from 'axios'

export default {
  getGalleries() {
    return axios.get(Routing.generate('api_user_gallery_list')).then((resp) => resp.data.member)
  },

  getGallery(id) {
    return axios.get(Routing.generate('api_user_gallery_get', { id })).then((resp) => resp.data)
  },

  getPreview(id) {
    return axios.get(Routing.generate('api_user_gallery_preview', { id })).then((resp) => resp.data)
  },

  create({ title }) {
    return axios
      .post(
        Routing.generate('api_user_gallery_add'),
        { title },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  },

  update(id, { title, description }) {
    return axios
      .patch(
        Routing.generate('api_user_gallery_edit', { id }),
        { title, description },
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
  },

  delete(id) {
    return axios.delete(Routing.generate('api_user_gallery_delete', { id })).then((resp) => resp.data)
  },

  getImages(id) {
    return axios.get(Routing.generate('api_user_gallery_images', { id })).then((resp) => resp.data.member)
  },

  uploadImage(id, file, onProgress) {
    const formData = new FormData()
    formData.append('imageFile', file)

    return axios
      .post(Routing.generate('api_user_gallery_upload_image', { id }), formData, {
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

  setCover(imageId) {
    return axios
      .patch(
        Routing.generate('api_user_gallery_image_cover', { id: imageId }),
        {},
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
  },

  deleteImage(imageId) {
    return axios
      .delete(Routing.generate('api_user_gallery_image_delete', { id: imageId }))
      .then((resp) => resp.data)
  },

  submit(id) {
    return axios
      .patch(
        Routing.generate('api_user_gallery_validation', { id }),
        {},
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
  }
}
