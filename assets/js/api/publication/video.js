/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Get a preview of a YouTube video
   * @param {string} url - The YouTube video URL
   * @returns {Promise<{url: string, title: string, description: string, imageUrl: string}>}
   */
  getPreview(url) {
    return axios
      .get(Routing.generate('api_publication_video_preview', { url }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * Add a new video discovery
   * @param {Object} params
   * @param {string} params.url - The YouTube video URL
   * @param {string} params.title - The video title
   * @param {string} params.description - The video description
   * @param {string|null} [params.categoryId] - Optional category ID
   * @returns {Promise<Object>}
   */
  addVideo({ url, title, description, categoryId = null }) {
    const payload = {
      url,
      title,
      description
    }

    if (categoryId) {
      payload.category = `/api/publication_sub_categories/${categoryId}`
    }

    return axios
      .post(Routing.generate('api_publication_video_add'), payload, {
        headers: {
          'Content-Type': 'application/ld+json',
          Accept: 'application/ld+json'
        }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
