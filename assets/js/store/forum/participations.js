import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import forumApi from '../../api/forum/forum.js'

export const useForumParticipationsStore = defineStore('forumParticipations', () => {
  const participations = ref([])
  const totalItems = ref(0)
  const isLoading = ref(false)

  async function loadParticipations({ page = 1 } = {}) {
    isLoading.value = true
    try {
      const data = await forumApi.getMyParticipations({ page })
      participations.value = data.member
      totalItems.value = data.totalItems
    } finally {
      isLoading.value = false
    }
  }

  async function removeParticipation(id) {
    await forumApi.removeParticipation(id)
    participations.value = participations.value.filter((p) => p.id !== id)
    totalItems.value = Math.max(0, totalItems.value - 1)
  }

  function clear() {
    participations.value = []
    totalItems.value = 0
  }

  return {
    participations: readonly(participations),
    totalItems: readonly(totalItems),
    isLoading: readonly(isLoading),
    loadParticipations,
    removeParticipation,
    clear
  }
})
