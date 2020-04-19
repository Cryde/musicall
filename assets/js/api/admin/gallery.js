import axios from 'axios';

export default {
  getPendingGalleries() {
    return axios.get(Routing.generate('api_admin_gallery_pending_list'))
    .then(resp => resp.data)
  },
  approveGallery(id) {
    return axios.get(Routing.generate('api_admin_gallery_approve', {id}))
    .then(resp => resp.data)
  },
  rejectGallery(id) {
    return axios.get(Routing.generate('api_admin_gallery_reject', {id}))
    .then(resp => resp.data)
  }
}