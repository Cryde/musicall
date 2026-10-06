import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import blockApi from '../../api/user/block.js'

/** The users the signed-in member has blocked (#1117), loaded once and kept in step with each change. */
export const useUserBlockStore = defineStore('userBlock', () => {
  const blocks = ref([])
  const isLoaded = ref(false)
  const isLoading = ref(false)
  let pendingLoad = null

  const blockedIds = computed(() => new Set(blocks.value.map((block) => block.user_id)))

  function loadBlocks() {
    if (isLoaded.value) {
      return Promise.resolve()
    }
    if (!pendingLoad) {
      isLoading.value = true
      pendingLoad = blockApi
        .list()
        .then((member) => {
          blocks.value = member
          isLoaded.value = true
        })
        .finally(() => {
          isLoading.value = false
          pendingLoad = null
        })
    }
    return pendingLoad
  }

  function isBlocked(userId) {
    return blockedIds.value.has(userId)
  }

  async function blockUser(userId) {
    const block = await blockApi.block(userId)
    blocks.value = [block, ...blocks.value.filter((existing) => existing.user_id !== userId)]
  }

  async function unblockUser(userId) {
    await blockApi.unblock(userId)
    blocks.value = blocks.value.filter((block) => block.user_id !== userId)
  }

  return {
    blocks: readonly(blocks),
    isLoaded: readonly(isLoaded),
    isLoading: readonly(isLoading),
    loadBlocks,
    isBlocked,
    blockUser,
    unblockUser
  }
})
