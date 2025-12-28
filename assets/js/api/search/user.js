/** global: Routing */

import axios from 'axios'

export default {
  /**
   * Search users by username
   * @param {string} search - Search term (min 3 characters)
   */
  searchUsers(search) {
    return axios
      .get(Routing.generate('api_users_search', { search }))
      .then((resp) => resp.data)
      .then((resp) => resp.member || [])
  }
}
