/** global: Routing */

import axios from 'axios';

export default {
  getCountries() {
    return axios.get(Routing.generate('api_attributes_countries'))
    .then(resp => resp.data);
  }
}