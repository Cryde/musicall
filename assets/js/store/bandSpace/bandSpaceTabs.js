import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceFilesApi from '../../api/bandSpace/band-space-files.js'
import {
  FILES_PAGE_SIZE,
  hasMoreToLoad,
  mergePage,
  nextPageToLoad
} from '../../utils/filePagination.js'
import { GUITAR_PRO_FORMAT, tabNamed } from '../../utils/tabList.js'

/** Every Guitar Pro file of the space, latest change first, so the last upload is on top. */
const TAB_LISTING = Object.freeze({
  format: GUITAR_PRO_FORMAT,
  sort: 'updated',
  order: 'desc',
  itemsPerPage: FILES_PAGE_SIZE
})

/** The Tablatures module (#1430): a view over the space's files, so every write goes through Files. */
export const useBandTabsStore = defineStore('bandTabs', () => {
  const tabs = ref([])
  const totalTabs = ref(0)
  const isLoading = ref(false)
  const isLoadingMore = ref(false)
  const loadError = ref(null)
  const loadMoreError = ref(null)

  let requestId = 0

  const hasMore = computed(() => hasMoreToLoad(tabs.value.length, totalTabs.value))

  /**
   * The first page, replacing the list. A quiet fetch (a live refresh) shows no loading state and keeps
   * what is on screen when it fails.
   */
  async function fetchTabs(bandSpaceId, { quiet = false } = {}) {
    const id = ++requestId
    if (!quiet) {
      isLoading.value = true
      loadError.value = null
    }
    loadMoreError.value = null

    try {
      const result = await bandSpaceFilesApi.getFiles(bandSpaceId, { ...TAB_LISTING, page: 1 })
      if (id !== requestId) return
      tabs.value = result.member ?? []
      totalTabs.value = result.totalItems ?? 0
      loadError.value = null
    } catch (e) {
      if (id !== requestId || quiet) return
      loadError.value = e.message
    } finally {
      if (id === requestId) isLoading.value = false
    }
  }

  /** Another member changed the files: the first page is read again unless the member scrolled past it. */
  function refreshFromLiveSignal(bandSpaceId) {
    if (tabs.value.length <= FILES_PAGE_SIZE) {
      fetchTabs(bandSpaceId, { quiet: true })
    }
  }

  async function fetchMoreTabs(bandSpaceId) {
    if (isLoadingMore.value || !hasMore.value) return

    const id = ++requestId
    isLoadingMore.value = true
    loadMoreError.value = null

    try {
      const result = await bandSpaceFilesApi.getFiles(bandSpaceId, {
        ...TAB_LISTING,
        page: nextPageToLoad(tabs.value.length, FILES_PAGE_SIZE)
      })
      if (id !== requestId) return
      const heldBefore = tabs.value.length
      tabs.value = mergePage(tabs.value, result.member ?? [])
      totalTabs.value = result.totalItems ?? totalTabs.value

      // A tab another member changed moved to the top, so this page held only rows already loaded and
      // the next one would be asked for again. See utils/filePagination.js.
      if (tabs.value.length === heldBefore && hasMore.value) {
        isLoadingMore.value = false
        await fetchTabs(bandSpaceId, { quiet: true })
        return
      }
    } catch (e) {
      if (id !== requestId) return
      loadMoreError.value = e.message
    } finally {
      isLoadingMore.value = false
    }
  }

  /**
   * The tab already carrying this name, or null. Asked of the server rather than read from the list,
   * which may not hold every page.
   */
  async function findTabNamed(bandSpaceId, name) {
    const result = await bandSpaceFilesApi.getFiles(bandSpaceId, {
      ...TAB_LISTING,
      query: name,
      page: 1
    })

    return tabNamed(result.member ?? [], name)
  }

  function clear() {
    requestId += 1
    tabs.value = []
    totalTabs.value = 0
    isLoading.value = false
    isLoadingMore.value = false
    loadError.value = null
    loadMoreError.value = null
  }

  return {
    tabs: readonly(tabs),
    totalTabs: readonly(totalTabs),
    hasMore,
    isLoading: readonly(isLoading),
    isLoadingMore: readonly(isLoadingMore),
    loadError: readonly(loadError),
    loadMoreError: readonly(loadMoreError),
    fetchTabs,
    refreshFromLiveSignal,
    fetchMoreTabs,
    findTabNamed,
    clear
  }
})
