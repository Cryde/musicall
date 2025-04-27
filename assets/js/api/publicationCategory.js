/** global: Routing */

import axios from 'axios';

export default {
  getCategories() {
    return axios.get(Routing.generate('api_publication_sub_categories_get_collection', {order: {'position': 'asc'}}))
    .then(resp => resp.data)
    .then(resp => resp['member']);
  }
}