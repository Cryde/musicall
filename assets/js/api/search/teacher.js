/** global: Routing */

import axios from 'axios'

export default {
  /** The teacher search (#1314), its params from teacherSearchParams(). */
  searchTeachers(params) {
    return axios
      .get(Routing.generate('api_services_teaching_search', params))
      .then((resp) => resp.data)
  },
  /** The instruments a listed teacher teaches, which the instrument filter offers. */
  getTaughtInstruments() {
    return axios
      .get(Routing.generate('api_services_teaching_instruments'))
      .then((resp) => resp.data.member)
  }
}
