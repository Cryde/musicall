export default {
  getPublication(slug) {
    return fetch(Routing.generate('api_publications_show', {slug}), {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
      },
    })
    .then(resp => resp.json())
  }
}