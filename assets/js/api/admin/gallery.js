/** global: Routing */

export default {
  getPendingGalleries() {
    return fetch(Routing.generate('api_admin_gallery_pending_list'), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  approveGallery(id) {
    return fetch(Routing.generate('api_admin_gallery_approve', {id}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  rejectGallery(id) {
    return fetch(Routing.generate('api_admin_gallery_reject', {id}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  }
}