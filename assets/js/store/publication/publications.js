import {defineStore} from 'pinia'
import {readonly, ref} from 'vue';
import publicationsApi from '../../api/publication/publications.js';

export const usePublicationsStore = defineStore('publicaton', () => {

  const publications = ref([]);
  const publicationCategories = ref([]);

  async function loadPublications({page = 1, slug = null, orientation = 'desc'}) {
    const publicationsResponse = await publicationsApi.getPublications({page, slug, orientation});

    publications.value = publicationsResponse.member;
  }

  async function loadCategories() {
    publicationCategories.value = await publicationsApi.getPublicationCategories();
  }

  function clear() {
    publications.value = [];
    publicationCategories.value = [];
  }

  return {
    loadPublications,
    loadCategories,
    clear,
    publications: readonly(publications),
    publicationCategories: readonly(publicationCategories)
  }
});
