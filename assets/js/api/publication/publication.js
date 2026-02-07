/** global: Routing */

import axios from 'axios'

export default {
  getPublication(slug) {
    return axios
      .get(Routing.generate('api_publication_get_item', { slug }))
      .then((resp) => resp.data)
  },

  getRelatedPublications(slug) {
    return axios
      .get(Routing.generate('api_publication_related', { slug }))
      .then((resp) => resp.data.member)
  },

  votePublication(slug, value) {
    return axios
      .post(
        Routing.generate('api_publication_vote_post', { slug }),
        { user_vote: value },
        {
          headers: { 'Content-Type': 'application/ld+json' }
        }
      )
      .then((resp) => resp.data)
  }
}
