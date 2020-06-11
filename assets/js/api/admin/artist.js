import axios from 'axios';

export default {
  addArtist({name}) {
    return axios.post(Routing.generate('api_admin_artist_add'), {name})
    .then(resp => resp.data);
  },
  listArtists() {
    return axios.get(Routing.generate('api_admin_artist_list'))
    .then(resp => resp.data);
  },
  getArtist({id}) {
    return axios.get(Routing.generate('api_admin_artist_show', {id}))
    .then(resp => resp.data);
  },
  edit({id, labelName, biography, members, socials}) {
    return axios.patch(Routing.generate('api_admin_artist_edit', {id}), {
      label_name: labelName,
      biography,
      members,
      socials
    })
    .then(resp => resp.data);
  },
}