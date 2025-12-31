import axios from 'axios'

export default {
  getCourses({
    page = 1,
    itemsPerPage = 10,
    status = null,
    category = null,
    sortBy = 'creation_datetime',
    sortOrder = 'desc'
  }) {
    const params = new URLSearchParams()
    params.append('page', page.toString())
    params.append('itemsPerPage', itemsPerPage.toString())

    if (status !== null && status !== '') {
      params.append('status', status.toString())
    }
    if (category !== null && category !== '') {
      params.append('category', category.toString())
    }
    if (sortBy) {
      params.append('sortBy', sortBy)
    }
    if (sortOrder) {
      params.append('sortOrder', sortOrder)
    }

    return axios
      .get(Routing.generate('api_user_courses_get_collection') + '?' + params.toString())
      .then((resp) => resp.data)
  },

  delete(id) {
    return axios
      .delete(Routing.generate('api_user_courses_delete', { id }))
      .then((resp) => resp.data)
  }
}
