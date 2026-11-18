/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

const JSON_LD = { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }

/** The « Réalisations » of a member's own service (#1407), and their images. */
export default {
  createWork(serviceId, data) {
    return axios
      .post(Routing.generate('api_user_services_works_create', { serviceId }), data, {
        headers: JSON_LD
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** A merge patch: workPayload() sends every field, so a cleared one is emptied. */
  updateWork(serviceId, workId, data) {
    return axios
      .patch(Routing.generate('api_user_services_works_patch', { serviceId, id: workId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteWork(serviceId, workId) {
    return axios
      .delete(Routing.generate('api_user_services_works_delete', { serviceId, id: workId }))
      .catch(handleApiError)
  },

  /** Every work's id, each once, in the new order. */
  reorderWorks(serviceId, ids) {
    return axios
      .post(
        Routing.generate('api_user_services_works_reorder', { serviceId }),
        { ids },
        {
          headers: JSON_LD
        }
      )
      .catch(handleApiError)
  },

  uploadImage(serviceId, workId, file) {
    const form = new FormData()
    form.append('file', file)
    return axios
      .post(Routing.generate('api_user_services_work_images_upload', { serviceId, workId }), form, {
        headers: { Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteImage(serviceId, workId, imageId) {
    return axios
      .delete(
        Routing.generate('api_user_services_work_images_delete', { serviceId, workId, id: imageId })
      )
      .catch(handleApiError)
  },

  reorderImages(serviceId, workId, ids) {
    return axios
      .post(
        Routing.generate('api_user_services_work_images_reorder', { serviceId, workId }),
        { ids },
        { headers: JSON_LD }
      )
      .catch(handleApiError)
  }
}
