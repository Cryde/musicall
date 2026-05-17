/** global: Routing */

import axios from 'axios'

export default {
  search(label) {
    return axios.get(Routing.generate('api_tags_list', { label })).then((resp) => resp.data.member)
  }
}
