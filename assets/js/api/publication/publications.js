/** global: Routing */

import axios from 'axios'

export default {
  getPublications({ page, slug = null, orientation = 'desc' }) {
    return axios
      .get(
        Routing.generate('api_publication_get_collection', {
          page,
          'sub_category.type': '1',
          order: { publication_datetime: orientation },
          'sub_category.slug': slug
        })
      )
      .then((resp) => resp.data)
  },
  getPublicationCategories() {
    return axios
      .get(
        Routing.generate('api_publication_sub_categories_get_collection', {
          type: 1,
          order: { position: 'asc' }
        })
      )
      .then((resp) => resp.data)
      .then((resp) => resp.member)
  }
}
