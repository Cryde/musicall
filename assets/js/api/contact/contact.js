/** global: Routing */

import axios from 'axios';

export default {
  send({name, message, email}) {
    return axios.post(Routing.generate('_api_contact_us'), {name, message, email},
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data);
  }
}