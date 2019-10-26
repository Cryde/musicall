export default {
  getAllByType({type = 1}) {
    return fetch(Routing.generate('api_publication_category_list'))
    .then(resp => resp.json())
  }
}