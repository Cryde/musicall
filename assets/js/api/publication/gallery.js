/** global: Routing */

import axios from 'axios';

export default {
  getGallery(slug) {
    return axios.get(Routing.generate('api_gallery_get_item', {slug}))
    .then(resp => resp.data)
  },
  getGalleries() {
    return axios.get(Routing.generate('api_gallery_get_collection', {order: {'publication_datetime': 'desc'}}))
    .then(resp => resp.data)
  },
  getGalleryImages(slug) {
    return axios.get(Routing.generate('api_gallery_images_show', {slug}))
    .then(resp => resp.data)
  },
  addGallery({title}) {
    return axios.post(Routing.generate('api_user_gallery_add'), {title})
    .then(resp => resp.data)
  },
  editGallery({title, id, description}) {
    return axios.post(Routing.generate('api_user_gallery_edit', {id}), {title, description})
    .then(resp => resp.data)
  },
  patchCoverGallery({imageId}) {
    return axios.patch(Routing.generate('api_user_gallery_image_cover', {id: imageId}))
    .then(resp => resp.data)
  },
  publishGallery(galleryId) {
    return axios.patch(Routing.generate('api_user_gallery_validation', {id: galleryId}))
    .then(resp => resp.data);
  },
  getUserGallery(id) {
    return axios.get(Routing.generate('api_user_gallery_get', {id}))
    .then(resp => resp.data)
  },
  getUserGalleries() {
    return axios.get(Routing.generate('api_user_gallery_list'))
    .then(resp => resp.data)
  },
  getUserImages(galleryId) {
    return axios.get(Routing.generate('api_user_gallery_images', {id: galleryId}))
    .then(resp => resp.data)
  },
  removeImage(id) {
    return axios.delete(Routing.generate('api_user_gallery_image_delete', {id}))
    .then(resp => resp.data)
  }
}