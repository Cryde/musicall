export default {
  getPublications({offset}) {
    return fetch(Routing.generate('api_publications_list', {offset}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  },
  getPublicationsByCategory({slug, offset}) {
    return fetch(Routing.generate('api_publications_list_by_category', {slug, offset}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  }
}