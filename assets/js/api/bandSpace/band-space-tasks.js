/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getTasks(bandSpaceId, { archived, query } = {}) {
    const params = {}
    if (archived !== undefined) params.archived = archived
    if (query) params.query = query
    return axios
      .get(Routing.generate('api_band_space_tasks_get_collection', { bandSpaceId }), { params })
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  getTask(bandSpaceId, taskId) {
    return axios
      .get(Routing.generate('api_band_space_tasks_get_item', { bandSpaceId, id: taskId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  createTask(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_tasks_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateTask(bandSpaceId, taskId, data) {
    return axios
      .patch(Routing.generate('api_band_space_tasks_patch', { bandSpaceId, id: taskId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteTask(bandSpaceId, taskId) {
    return axios
      .delete(Routing.generate('api_band_space_tasks_delete', { bandSpaceId, id: taskId }))
      .catch(handleApiError)
  },

  getCategories(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_task_categories_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createCategory(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_task_categories_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateCategory(bandSpaceId, categoryId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_task_categories_patch', { bandSpaceId, id: categoryId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteCategory(bandSpaceId, categoryId) {
    return axios
      .delete(
        Routing.generate('api_band_space_task_categories_delete', { bandSpaceId, id: categoryId })
      )
      .catch(handleApiError)
  },

  reorderTasks(bandSpaceId, positions) {
    return axios
      .post(
        Routing.generate('api_band_space_tasks_reorder', { bandSpaceId }),
        { positions },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  moveTask(bandSpaceId, taskId, status, positions) {
    return axios
      .post(
        Routing.generate('api_band_space_tasks_move', { bandSpaceId }),
        { task_id: taskId, status, positions },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  getComments(bandSpaceId, taskId) {
    return axios
      .get(Routing.generate('api_band_space_task_comments_get_collection', { bandSpaceId, taskId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createComment(bandSpaceId, taskId, data) {
    return axios
      .post(Routing.generate('api_band_space_task_comments_post', { bandSpaceId, taskId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateComment(bandSpaceId, taskId, commentId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_task_comments_patch', {
          bandSpaceId,
          taskId,
          id: commentId
        }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteComment(bandSpaceId, taskId, commentId) {
    return axios
      .delete(
        Routing.generate('api_band_space_task_comments_delete', {
          bandSpaceId,
          taskId,
          id: commentId
        })
      )
      .catch(handleApiError)
  },

  getActivities(bandSpaceId, taskId) {
    return axios
      .get(
        Routing.generate('api_band_space_task_activities_get_collection', { bandSpaceId, taskId })
      )
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  }
}
