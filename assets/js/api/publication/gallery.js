/** global: Routing */

export default {
  addGallery({title}) {
    return fetch(Routing.generate('api_user_gallery_add'), {
      method: 'POST',
      body: JSON.stringify({title})
    })
    .then(resp => resp.json())
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
  }
}