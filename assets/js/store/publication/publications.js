import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import publicationsApi from '../../api/publication/publications.js'

export const usePublicationsStore = defineStore('publications', () => {
  const publications = ref([])
  const lastPublications = ref([])
  const publicationCategories = ref([])

  async function loadPublications({ page = 1, slug = null, orientation = 'desc' }) {
    const { member } = await publicationsApi.getPublications({ page, slug, orientation })

    publications.value = [...publications.value, ...member]
    return member
  }

  async function loadLastPublications() {
    lastPublications.value = await publicationsApi.getLastPublications()
  }

  async function loadCategories() {
    publicationCategories.value = await publicationsApi.getPublicationCategories()
  }

  function clear() {
    publications.value = []
    lastPublications.value = []
    publicationCategories.value = []
  }

  function resetPublications() {
    publications.value = []
  }

  return {
    loadPublications,
    loadLastPublications,
    loadCategories,
    clear,
    resetPublications,
    publications: readonly(publications),
    lastPublications: readonly(lastPublications),
    publicationCategories: readonly(publicationCategories)
  }
})
