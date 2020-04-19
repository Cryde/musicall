/** global: Routing */

import axios from 'axios';

export default {
  getPublications({offset}) {
    return axios.get(Routing.generate('api_publications_list', {offset}))
    .then(resp => resp.data)
  },
  getPublicationsByCategory({slug, offset}) {
    return axios.get(Routing.generate('api_publications_list_by_category', {slug, offset}))
    .then(resp => resp.data)
  }
}