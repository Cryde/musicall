/** global: Routing */

import axios from 'axios'

export default {
  /** Always a 204, a repeat while the first report is pending included: the reporter learns nothing. */
  create({ targetType, targetId, reason, details }) {
    return axios.post(
      Routing.generate('api_reports_post'),
      { target_type: targetType, target_id: targetId, reason, details: details || null },
      { headers: { 'Content-Type': 'application/ld+json' } }
    )
  }
}
