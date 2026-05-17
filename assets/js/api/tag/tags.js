/** global: Routing */

import axios from 'axios'

export default {
  getPopularTags({ count = 8 } = {}) {
    return axios
      .get(Routing.generate('api_tag_get_popular', { count }))
      .then((resp) => resp.data.member)
  }
}
