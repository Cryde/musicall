import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import publicationApi from '../../api/publication/publication.js'

export const usePublicationStore = defineStore('publicaton', () => {
  const publication = ref(null)
  const relatedPublications = ref([])

  async function loadPublication(slug) {
    publication.value = await publicationApi.getPublication(slug)
  }

  async function loadRelatedPublications(slug) {
    relatedPublications.value = await publicationApi.getRelatedPublications(slug)
  }

  function clear() {
    publication.value = null
    relatedPublications.value = []
  }

  return {
    loadPublication,
    loadRelatedPublications,
    publication: readonly(publication),
    relatedPublications: readonly(relatedPublications),
    clear
  }
})
