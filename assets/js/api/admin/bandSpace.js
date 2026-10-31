/** global: Routing */

import axios from 'axios'

/** How Band Spaces are used, for the admin (#1275). */
export default {
  getOverview(from, to) {
    return axios
      .get(Routing.generate('api_admin_band_spaces_overview', { from, to }))
      .then((resp) => resp.data)
  }
}
