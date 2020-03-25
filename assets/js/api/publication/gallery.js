/** global: Routing */

export default {
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
  getGallery(id) {
    return fetch(Routing.generate('api_user_gallery_get', {id}))
    .then(resp => resp.json())
  },
  getGalleries() {
    return fetch(Routing.generate('api_user_gallery_list'))
    .then(resp => resp.json())
  },
  getImages(galleryId) {
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