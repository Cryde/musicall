export default {
  getPublications() {
    return fetch(Routing.generate('api_publications_list'), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  getPublicationsByCategory({slug}) {
    return fetch(Routing.generate('api_publications_list_by_category', {slug}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  }
}