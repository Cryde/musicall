/** global: Routing */

import axios from 'axios';

export default {
  getCategories() {
    return axios.get(Routing.generate('api_publication_category_list'))
    .then(resp => resp.data)
  }
}