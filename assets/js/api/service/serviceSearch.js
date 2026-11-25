/** global: Routing */

import axios from 'axios'

/** A search by speciality (#1410, #1411): its route from SPECIALITY_SEARCHES, its params from specialitySearchParams(). */
export default {
  search(routeName, params) {
    return axios.get(Routing.generate(routeName, params)).then((resp) => resp.data)
  }
}
