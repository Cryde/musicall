/** global: Routing */

import axios from 'axios';

export default {
  saveFeatured({publicationId, level, title, description}) {
    return axios.post(Routing.generate('api_admin_publication_featured_add', {id: publicationId}), {
      level,
      title,
      description
    },
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data);
  },
  editFeatured({featuredId, title, description}) {
    return axios.post(Routing.generate('api_admin_publication_featured_edit', {id: featuredId}), {title, description},
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        })
    .then(resp => resp.data);
  },
  removeFeatured(featuredId) {
    return axios.delete(Routing.generate('api_admin_publication_featured_delete', {id: featuredId}));
  },
  getFeaturedList() {
    return axios.get(Routing.generate('api_publication_featureds_get_collection'))
    .then(resp => resp.data);
  },
  changeOption({featuredId, option, value}) {
    return axios.patch(Routing.generate('api_admin_publication_featured_options', {id: featuredId, [option]: value}))
    .then(resp => resp.data);
  },
  publish(featuredId) {
    return axios.patch(Routing.generate('api_admin_publication_featured_publish', {id: featuredId}))
    .then(resp => resp.data);
  },
  unpublish(featuredId) {
    return axios.patch(Routing.generate('api_admin_publication_featured_unpublish', {id: featuredId}))
    .then(resp => resp.data);
  }
}