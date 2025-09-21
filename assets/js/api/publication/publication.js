/** global: Routing */

import axios from 'axios';

export default {
  getPublication(slug) {
    return axios.get(Routing.generate('api_publication_get_item', {slug}))
    .then(resp => resp.data)
  },
}