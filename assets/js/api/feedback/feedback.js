/** global: Routing */

import axios from 'axios'

export default {
  send({ type, module, message, email, pageUrl, bandSpaceId }) {
    return axios
      .post(
        Routing.generate('api_feedback_post'),
        {
          type,
          module,
          message,
          email,
          page_url: pageUrl,
          band_space_id: bandSpaceId
        },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  }
}
