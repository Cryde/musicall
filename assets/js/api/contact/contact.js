/** global: Routing */

import axios from 'axios';

export default {
  send({name, message, email}) {
    return axios.post(Routing.generate('api_contact'), {name, message, email},
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data);
  }
}