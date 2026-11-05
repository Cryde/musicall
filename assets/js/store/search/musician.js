import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import searchApi from '../../api/search/musician.js'

export const useMusicianSearchStore = defineStore('musicianSearch', () => {
  const announces = ref([])
  const filters = ref(null)
  const currentPage = ref(1)
  const lastBatchSize = ref(0)
  // The filters search as they change, so two answers can cross: only the latest one shows, and the
  // caller learns whether its own did.
  let latestRequest = 0

  async function searchAnnounces({
    type = null,
    instrument = null,
    styles = null,
    latitude = null,
    longitude = null,
    location = null,
    radius = null,
    language = null,
    landing = false,
    page = 1,
    append = false
  }) {
    const request = ++latestRequest
    const data = await searchApi.searchAnnounces({
      instrument,
      styles,
      type,
      latitude,
      longitude,
      location,
      radius,
      language,
      landing,
      page
    })
    if (request !== latestRequest) return false

    if (append) {
      announces.value = [...announces.value, ...data.member]
    } else {
      announces.value = data.member
    }
    lastBatchSize.value = data.member.length
    currentPage.value = page
    return true
  }

  /**
   * Records a search the page already shows (#1075): the API keeps a search through the search
   * request itself, so the same request goes again, without `landing`, and its answer is not needed.
   */
  async function recordSearch(params) {
    await searchApi.searchAnnounces({ ...params, landing: false, page: 1 })
  }

  async function getSearchAnnouncesFilters({ search }) {
    filters.value = null
    filters.value = await searchApi.getSearchAnnouncesFilters({ search })
  }

  function clear() {
    announces.value = []
    currentPage.value = 1
    lastBatchSize.value = 0
  }

  return {
    searchAnnounces,
    recordSearch,
    getSearchAnnouncesFilters,
    clear,
    announces: readonly(announces),
    filters: readonly(filters),
    currentPage: readonly(currentPage),
    lastBatchSize: readonly(lastBatchSize)
  }
})
