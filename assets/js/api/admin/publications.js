/** global: Routing */

export default {
  getPendingPublications() {
    return fetch(Routing.generate('api_admin_publications_pending_list'), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  approvePublication(id) {
    return fetch(Routing.generate('api_admin_publications_approve', {id}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  rejectPublication(id) {
    return fetch(Routing.generate('api_admin_publications_reject', {id}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  }
}