import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import soundEngineerApi from '../../api/service/soundEngineer.js'

/** The sound engineer search's results (#1410), kept while a visitor opens a page and comes back. */
export const useSoundEngineerSearchStore = defineStore('soundEngineerSearch', () => {
  const engineers = ref([])
  const totalItems = ref(0)
  const currentPage = ref(1)
  // The filters search as they change, so two answers can cross: only the latest one shows, and the
  // caller learns whether its own did.
  let latestRequest = 0

  /** `params` from soundEngineerSearchParams(); a page past the first is added to the list on screen. */
  async function search(params) {
    const request = ++latestRequest
    const data = await soundEngineerApi.search(params)
    if (request !== latestRequest) return false

    const page = params.page ?? 1
    engineers.value = page > 1 ? [...engineers.value, ...data.member] : data.member
    totalItems.value = data.totalItems
    currentPage.value = page
    return true
  }

  const hasMore = computed(() => engineers.value.length < totalItems.value)

  return {
    search,
    engineers: readonly(engineers),
    totalItems: readonly(totalItems),
    currentPage: readonly(currentPage),
    hasMore
  }
})
