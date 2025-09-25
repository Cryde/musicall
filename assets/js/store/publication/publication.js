import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import publicationApi from '../../api/publication/publication.js'

export const usePublicationStore = defineStore('publicaton', () => {
  const publication = ref(null)

  async function loadPublication(slug) {
    publication.value = await publicationApi.getPublication(slug)
  }

  function clear() {
    publication.value = null
  }

  return {
    loadPublication,
    publication: readonly(publication),
    clear
  }
})
