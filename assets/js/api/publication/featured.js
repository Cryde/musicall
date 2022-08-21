/** global: Routing */

import axios from 'axios';

export default {
  getFeaturedList() {
    return axios.get(Routing.generate('api_publication_featureds_get_collection', {status: 1}))
    .then(resp => resp.data);
  }
}