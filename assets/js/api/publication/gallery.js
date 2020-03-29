/** global: Routing */

export default {
  getGallery(slug) {
    return fetch(Routing.generate('api_gallery_show', {slug}))
    .then(resp => resp.json())
  },
  getGalleries() {
    return fetch(Routing.generate('api_gallery_list'))
    .then(resp => resp.json())
  },
  getGalleryImages(slug) {
    return fetch(Routing.generate('api_gallery_images_show', {slug}))
    .then(resp => resp.json())
  },
  addGallery({title}) {
    return fetch(Routing.generate('api_user_gallery_add'), {
      method: 'POST',
      body: JSON.stringify({title})
    })
    .then(resp => resp.json())
  },
  editGallery({title, id}) {
    return fetch(Routing.generate('api_user_gallery_edit', {id}), {
      method: 'POST',
      body: JSON.stringify({title})
    })
    .then(resp => resp.json())
  },
  patchCoverGallery({imageId}) {
    return fetch(Routing.generate('api_user_gallery_image_cover', {id: imageId}), {
      method: 'PATCH',
    })
    .then(resp => resp.json())
  },
  publishGallery(galleryId) {
    return fetch(Routing.generate('api_user_gallery_publish', {id: galleryId}), {
      method: 'PATCH',
    })
    .then(async (resp) => {
      const json = await resp.json();
      return resp.ok ? json : Promise.reject(json);
    });
  },
  getUserGallery(id) {
    return fetch(Routing.generate('api_user_gallery_get', {id}))
    .then(resp => resp.json())
  },
  getUserGalleries() {
    return fetch(Routing.generate('api_user_gallery_list'))
    .then(resp => resp.json())
  },
  getUserImages(galleryId) {
    return fetch(Routing.generate('api_user_gallery_images', {id: galleryId}))
    .then(resp => resp.json())
  },
  removeImage(id) {
    return fetch(Routing.generate('api_user_gallery_image_delete', {id}), {
      method: 'DELETE'
    })
    .then(resp => resp.json())
  }
}