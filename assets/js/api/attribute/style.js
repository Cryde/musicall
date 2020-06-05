import axios from 'axios';

export default {
  listStyle() {
    return axios.get(Routing.generate('api_attributes_styles'))
    .then(resp => resp.data);
  }
}