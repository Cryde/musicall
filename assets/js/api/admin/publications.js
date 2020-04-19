import axios from 'axios';

export default {
  getPendingPublications() {
    return axios.get(Routing.generate('api_admin_publications_pending_list'))
    .then(resp => resp.data)
  },
  approvePublication(id) {
    return axios.get(Routing.generate('api_admin_publications_approve', {id}))
    .then(resp => resp.data)
  },
  rejectPublication(id) {
    return axios.get(Routing.generate('api_admin_publications_reject', {id}))
    .then(resp => resp.data)
  }
}