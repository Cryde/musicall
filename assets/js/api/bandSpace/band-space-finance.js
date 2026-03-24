/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getCategories(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_finance_categories_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createCategory(bandSpaceId, data) {
    return axios
      .post(
        Routing.generate('api_band_space_finance_categories_post', { bandSpaceId }),
        data,
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateCategory(bandSpaceId, categoryId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_finance_categories_patch', { bandSpaceId, id: categoryId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteCategory(bandSpaceId, categoryId) {
    return axios
      .delete(Routing.generate('api_band_space_finance_categories_delete', { bandSpaceId, id: categoryId }))
      .catch(handleApiError)
  },

  getEntries(bandSpaceId, from = null, to = null) {
    const params = {}
    if (from) params.from = from
    if (to) params.to = to
    return axios
      .get(Routing.generate('api_band_space_finance_entries_get_collection', { bandSpaceId }), { params })
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createEntry(bandSpaceId, data) {
    return axios
      .post(
        Routing.generate('api_band_space_finance_entries_post', { bandSpaceId }),
        data,
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateEntry(bandSpaceId, entryId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_finance_entries_patch', { bandSpaceId, id: entryId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteEntry(bandSpaceId, entryId) {
    return axios
      .delete(Routing.generate('api_band_space_finance_entries_delete', { bandSpaceId, id: entryId }))
      .catch(handleApiError)
  },

  getSplits(bandSpaceId, entryId) {
    return axios
      .get(Routing.generate('api_band_space_finance_entry_splits_get_collection', { bandSpaceId, entryId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createSplit(bandSpaceId, entryId, data) {
    return axios
      .post(
        Routing.generate('api_band_space_finance_entry_splits_post', { bandSpaceId, entryId }),
        data,
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteSplit(bandSpaceId, entryId, splitId) {
    return axios
      .delete(Routing.generate('api_band_space_finance_entry_splits_delete', { bandSpaceId, entryId, id: splitId }))
      .catch(handleApiError)
  },

  getSummary(bandSpaceId, from = null, to = null) {
    const params = {}
    if (from) params.from = from
    if (to) params.to = to
    return axios
      .get(Routing.generate('api_band_space_finance_summary', { bandSpaceId }), { params })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  getRecurrences(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_finance_recurrences_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  createRecurrence(bandSpaceId, data) {
    return axios
      .post(
        Routing.generate('api_band_space_finance_recurrences_post', { bandSpaceId }),
        data,
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateRecurrence(bandSpaceId, recurrenceId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_finance_recurrences_patch', { bandSpaceId, id: recurrenceId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteRecurrence(bandSpaceId, recurrenceId) {
    return axios
      .delete(Routing.generate('api_band_space_finance_recurrences_delete', { bandSpaceId, id: recurrenceId }))
      .catch(handleApiError)
  },

  bootstrap(bandSpaceId) {
    return axios
      .post(
        Routing.generate('api_band_space_finance_bootstrap', { bandSpaceId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
