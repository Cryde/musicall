/** global: Routing */

import axios from 'axios';

export default {
  send({name, message, email}) {
    return axios.post(Routing.generate('api_contact'), {name, message, email})
    .then(resp => resp.data);
  }
}