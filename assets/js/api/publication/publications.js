/** global: Routing */

import axios from 'axios'

export default {
  getPublications({ page, slug = null, orientation = 'desc' }) {
    return axios
      .get(
        Routing.generate('api_publication_get_collection', {
          page,
          'sub_category.type': '1',
          order: { publication_datetime: orientation },
          'sub_category.slug': slug
        })
      )
      .then((resp) => resp.data)
  },
  getPublicationsByTag({ slug, page = 1, orientation = 'desc' }) {
    return axios
      .get(
        Routing.generate('api_publication_get_collection', {
          page,
          order: { publication_datetime: orientation },
          'tag.slug': slug
        })
      )
      .then((resp) => resp.data)
  },
  getLastPublications() {
    return axios
      .get(Routing.generate('api_publication_get_last'))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
  },
  getLatestPublications({ excludeId = null, count = 3, subCategoryType = null } = {}) {
    const params = { count }
    if (excludeId !== null && excludeId !== undefined) {
      params.excludeId = excludeId
    }
    if (subCategoryType !== null && subCategoryType !== undefined) {
      params.subCategoryType = subCategoryType
    }
    return axios
      .get(Routing.generate('api_publication_get_latest', params))
      .then((resp) => resp.data.member)
  },
  getPublicationCategories() {
    return axios
      .get(Routing.generate('api_publication_categories_list'))
      .then((resp) => resp.data.member)
  }
}
