/** global: Routing */

import axios from 'axios';

export default {
  getCourses({page, slug = null, orientation = 'desc'}) {
    return axios.get(Routing.generate('api_publication_get_collection', {
      page,
      'sub_category.type': '2',
      order: {publication_datetime: orientation},
      'sub_category.slug': slug
    }))
    .then(resp => resp.data);
  },
  getCourseCategories() {
    return axios.get(Routing.generate('api_publication_sub_categories_get_collection', {type: 2, order: {'position': 'asc'}}))
    .then(resp => resp.data)
    .then(resp => resp['member']);
  }
}