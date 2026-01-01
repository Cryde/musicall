/** global: Routing */

import axios from 'axios'

export default {
  getGalleries({ page = 1, orientation = 'desc' } = {}) {
    return axios
      .get(
        Routing.generate('api_gallery_get_collection', {
          page,
          order: { publicationDatetime: orientation }
        })
      )
      .then((resp) => resp.data)
  },

  getGallery(slug) {
    return axios.get(Routing.generate('api_gallery_get_item', { slug })).then((resp) => resp.data)
  }
}
