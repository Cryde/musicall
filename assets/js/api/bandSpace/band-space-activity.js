/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Fetches the band-space activity feed.
   * @param {string} bandSpaceId
   * @param {Object} filters
   * @param {string[]} [filters.modules]
   * @param {string|null} [filters.actorId]
   * @param {string|null} [filters.type]
   * @param {string|null} [filters.from]   ISO datetime
   * @param {string|null} [filters.to]     ISO datetime
   * @param {number} [filters.page]        1-indexed
   */
  list(bandSpaceId, filters = {}) {
    const params = new URLSearchParams()

    for (const module of filters.modules ?? []) {
      params.append('module[]', module)
    }
    if (filters.actorId) {
      params.set('actor_id', filters.actorId)
    }
    if (filters.type) {
      params.set('type', filters.type)
    }
    if (filters.from) {
      params.set('from', filters.from)
    }
    if (filters.to) {
      params.set('to', filters.to)
    }
    if (filters.page) {
      params.set('page', String(filters.page))
    }

    const query = params.toString()
    const base = Routing.generate('api_band_space_activities_get_collection', { bandSpaceId })
    const url = query ? `${base}?${query}` : base

    return axios
      .get(url, { headers: { Accept: 'application/ld+json' } })
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
