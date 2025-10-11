import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import searchApi from '../../api/search/musician.js'

export const useMusicianSearchStore = defineStore('musicianSearch', () => {
  const announces = ref([])

  async function searchAnnounces({ type, instrument, styles = null }) {
    announces.value = []
    const data = await searchApi.searchAnnounces({ instrument, styles, type })

    announces.value = data.member
  }

  function clear() {
    announces.value = []
  }

  return {
    searchAnnounces,
    clear,
    announces: readonly(announces)
  }
})
