/** global: Routing */

import axios from 'axios'

export default {
  /** A search by speciality (#1410, #1411): its route from SPECIALITY_SEARCHES, its params from specialitySearchParams(). */
  search(routeName, params) {
    return axios.get(Routing.generate(routeName, params)).then((resp) => resp.data)
  },
  /** How many services each kind's search lists, for the annuaire (#1414): `{ kind, count }`, every kind. */
  getKindCounts() {
    return axios.get(Routing.generate('api_services_kinds')).then((resp) => resp.data.member)
  }
}
