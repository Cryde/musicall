import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import searchApi from '../../api/search/musician.js'

export const useMusicianSearchStore = defineStore('musicianSearch', () => {
  const announces = ref([])
  const filters = ref(null)
  const currentPage = ref(1)
  const lastBatchSize = ref(0)

  async function searchAnnounces({
    type = null,
    instrument = null,
    styles = null,
    latitude = null,
    longitude = null,
    page = 1,
    append = false
  }) {
    if (!append) {
      announces.value = []
      currentPage.value = 1
    }

    const data = await searchApi.searchAnnounces({ instrument, styles, type, latitude, longitude, page })

    if (append) {
      announces.value = [...announces.value, ...data.member]
    } else {
      announces.value = data.member
    }
    lastBatchSize.value = data.member.length
    currentPage.value = page
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
    getSearchAnnouncesFilters,
    clear,
    announces: readonly(announces),
    filters: readonly(filters),
    currentPage: readonly(currentPage),
    lastBatchSize: readonly(lastBatchSize)
  }
})
