/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Fetches all band spaces for the current user
   * @returns {Promise<Array>} List of band spaces
   */
  getMyBandSpaces() {
    return axios
      .get(Routing.generate('api_band_spaces_get_collection'))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  /**
   * Creates a new band space
   * @param {string} name - The name of the band space
   * @returns {Promise<Object>} The created band space
   */
  create(name) {
    return axios
      .post(
        Routing.generate('api_band_spaces_post_collection'),
        { name },
        {
          headers: {
            'Content-Type': 'application/ld+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * Renames a band space. Admin only, the server refuses a plain member.
   * @param {string} id - The band space id
   * @param {string} name - The new name
   * @returns {Promise<Object>} The updated band space
   */
  rename(id, name) {
    return axios
      .patch(
        Routing.generate('api_band_spaces_patch', { id }),
        { name },
        {
          headers: {
            'Content-Type': 'application/merge-patch+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
