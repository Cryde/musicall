/** global: Routing */

import axios from "axios";

export default {
  getRootForumCategory() {
    return axios.get(Routing.generate('api_forum_categories_get_collection', {
      order: {position: 'asc', 'forums.position': 'asc'},
      forum_slug: 'root'
    }))
    .then(resp => resp.data)
    .then(resp => resp['member']);
  },
  getForum(slug) {
    return axios.get(Routing.generate('api_forums_get_item', {slug}))
    .then(resp => resp.data);
  },
  getTopicsByForum(params) {
    return axios.get(Routing.generate('api_forum_topics_get_collection', params))
    .then(resp => resp.data);
  },
  getPostsByTopic(params) {
    return axios.get(Routing.generate('api_forum_posts_get_collection', params))
    .then(resp => resp.data);
  },
  getTopic(slug) {
    return axios.get(Routing.generate('api_forum_topics_get_item', {slug}))
    .then(resp => resp.data);
  },
  postTopicMessage(data) {
    return axios.post(Routing.generate('api_forum_topic_post_post'), data,
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data)
  },
  postPostMessage(data) {
    return axios.post(Routing.generate('api_forum_posts_post'), data,
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data)
  }
}