/** global: Routing */

import axios from 'axios';

export default {
  getPublications({page}) {
    return axios.get(Routing.generate('api_publication_get_collection', {page, order: {publication_datetime: 'desc'}}))
    .then(resp => resp.data);
  },
  getPublicationsByCategory({page, slug}) {
    return axios.get(Routing.generate('api_publication_get_collection', {
      page,
      order: {publication_datetime: 'desc'},
      'sub_category.slug': slug
    }))
    .then(resp => resp.data);
  }
}