import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import searchApi from '../../api/search/musician.js'

export const useMusicianSearchStore = defineStore('musicianSearch', () => {
  const announces = ref([])
  const filters = ref(null)

  async function searchAnnounces({ type, instrument, styles = null }) {
    announces.value = []
    const data = await searchApi.searchAnnounces({ instrument, styles, type })

    announces.value = data.member
  }

  async function getSearchAnnouncesFilters({ search }) {
    filters.value = null
    filters.value = await searchApi.getSearchAnnouncesFilters({ search })
  }

  function clear() {
    announces.value = []
  }

  return {
    searchAnnounces,
    getSearchAnnouncesFilters,
    clear,
    announces: readonly(announces),
    filters: readonly(filters)
  }
})
