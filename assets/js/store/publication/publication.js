import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import publicationApi from '../../api/publication/publication.js'

export const usePublicationStore = defineStore('publicaton', () => {
  const publication = ref(null)
  const relatedPublications = ref([])
  const isVoting = ref(false)

  async function loadPublication(slug) {
    publication.value = await publicationApi.getPublication(slug)
  }

  async function loadRelatedPublications(slug) {
    relatedPublications.value = await publicationApi.getRelatedPublications(slug)
  }

  async function vote(slug, value) {
    isVoting.value = true
    try {
      const voteData = await publicationApi.votePublication(slug, value)
      updateVoteState(voteData)
    } finally {
      isVoting.value = false
    }
  }

  function updateVoteState(voteData) {
    if (publication.value) {
      publication.value = {
        ...publication.value,
        upvotes: voteData.upvotes,
        downvotes: voteData.downvotes,
        user_vote: voteData.user_vote,
      }
    }
  }

  function clear() {
    publication.value = null
    relatedPublications.value = []
  }

  return {
    loadPublication,
    loadRelatedPublications,
    vote,
    publication: readonly(publication),
    relatedPublications: readonly(relatedPublications),
    isVoting: readonly(isVoting),
    clear
  }
})
