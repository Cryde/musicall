import axios from 'axios';

export default {
  addArtist({name}) {
    return axios.post(Routing.generate('api_admin_artist_add'), {name})
    .then(resp => resp.data);
  },
  listArtists() {
    return axios.get(Routing.generate('api_admin_artist_list'))
    .then(resp => resp.data);
  }
}