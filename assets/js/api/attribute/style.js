/** global: Routing */

import axios from 'axios';

export default {
  listStyle() {
    return axios.get(Routing.generate('api_styles_get_collection'))
    .then(resp => resp.data)
    .then(resp => resp['member']);
  }
}