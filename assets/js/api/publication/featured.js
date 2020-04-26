/** global: Routing */

import axios from 'axios';

export default {
  getFeaturedList() {
    return axios.get(Routing.generate('api_publication_featured_list'))
    .then(resp => resp.data);
  }
}