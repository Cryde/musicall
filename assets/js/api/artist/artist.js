/** global: Routing */

import axios from 'axios';

export default {
  getArtist({slug}) {
    return axios.get(Routing.generate('api_artist_show', {slug}))
    .then(resp => resp.data);
  }
}