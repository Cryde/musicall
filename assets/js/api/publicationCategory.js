/** global: Routing */

import axios from 'axios';

export default {
  getAllByType({type = 1}) {
    return axios.get(Routing.generate('api_publication_category_list'))
    .then(resp => resp.data)
  }
}