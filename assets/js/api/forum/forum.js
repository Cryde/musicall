/** global: Routing */

import axios from 'axios'

export default {
  getCategories() {
    return axios
      .get(Routing.generate('api_forum_categories_list'))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
  },

  getForum(slug) {
    return axios.get(Routing.generate('api_forum_detail', { slug })).then((resp) => resp.data)
  },

  getTopicsByForum({ forumSlug, page = 1 }) {
    return axios
      .get(Routing.generate('api_forum_topics_list', { slug: forumSlug, page }))
      .then((resp) => resp.data)
  },

  getTopic(slug) {
    return axios.get(Routing.generate('api_forum_topic_get', { slug })).then((resp) => resp.data)
  },

  getPostsByTopic({ topicSlug, page = 1 }) {
    return axios
      .get(Routing.generate('api_forum_topic_posts_list', { slug: topicSlug, page }))
      .then((resp) => resp.data)
  },

  createTopic(data) {
    return axios
      .post(Routing.generate('api_forum_topic_post_post'), data, {
        headers: { 'Content-Type': 'application/ld+json' }
      })
      .then((resp) => resp.data)
  },

  createPost(data) {
    return axios
      .post(Routing.generate('api_forum_posts_post'), data, {
        headers: { 'Content-Type': 'application/ld+json' }
      })
      .then((resp) => resp.data)
  },

  voteForumPost(id, value) {
    return axios
      .post(
        Routing.generate('api_forum_post_vote_post', { id }),
        { user_vote: value },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  }
}
