import { defineStore } from 'pinia'
import { reactive, readonly, ref } from 'vue'
import bandSpaceFilesApi from '../../api/bandSpace/band-space-files.js'

export const useBandFilesStore = defineStore('bandFiles', () => {
  const files = ref([])
  const totalFiles = ref(0)
  const folders = ref([])
  const virtualFolders = ref([])
  const tags = ref([])
  const quota = ref(null)
  const activeFolderId = ref(null)

  const filters = reactive({
    query: '',
    mime: null,
    tagId: null,
    source: null,
    sort: 'date',
    order: 'desc'
  })

  const isLoadingFiles = ref(false)
  const isLoadingFolders = ref(false)
  const isLoadingTags = ref(false)
  const isLoadingQuota = ref(false)
  const loadError = ref(null)

  let filesRequestId = 0
  let foldersRequestId = 0

  async function fetchFiles(bandSpaceId) {
    const requestId = ++filesRequestId
    isLoadingFiles.value = files.value.length === 0
    loadError.value = null

    const params = buildFileParams()

    try {
      const result = await bandSpaceFilesApi.getFiles(bandSpaceId, params)
      if (requestId !== filesRequestId) return
      files.value = result.member ?? []
      totalFiles.value = result.totalItems ?? 0
    } catch (e) {
      if (requestId !== filesRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === filesRequestId) {
        isLoadingFiles.value = false
      }
    }
  }

  async function fetchFolders(bandSpaceId) {
    const requestId = ++foldersRequestId
    isLoadingFolders.value = true

    try {
      const result = await bandSpaceFilesApi.getFolderTree(bandSpaceId)
      if (requestId !== foldersRequestId) return
      folders.value = result.member ?? []
      virtualFolders.value = result.virtualFolders ?? []
    } catch {
      // silently fail — folders are optional
    } finally {
      if (requestId === foldersRequestId) {
        isLoadingFolders.value = false
      }
    }
  }

  async function fetchTags(bandSpaceId) {
    isLoadingTags.value = true
    try {
      tags.value = await bandSpaceFilesApi.getTags(bandSpaceId)
    } catch {
      // silently fail
    } finally {
      isLoadingTags.value = false
    }
  }

  async function fetchQuota(bandSpaceId) {
    isLoadingQuota.value = true
    try {
      quota.value = await bandSpaceFilesApi.getQuota(bandSpaceId)
    } catch {
      // silently fail
    } finally {
      isLoadingQuota.value = false
    }
  }

  function buildFileParams() {
    const params = {}
    const trimmed = filters.query.trim()
    if (trimmed) params.query = trimmed
    if (filters.mime) params.mime = filters.mime
    if (filters.tagId) params.tagId = filters.tagId
    if (filters.sort) params.sort = filters.sort
    if (filters.order) params.order = filters.order

    if (activeFolderId.value === 'virtual:task') {
      params.source = 'task'
    } else if (activeFolderId.value === 'virtual:finance') {
      params.source = 'finance'
    } else if (activeFolderId.value) {
      params.folderId = activeFolderId.value
    } else if (filters.source) {
      params.source = filters.source
    }

    return params
  }

  function setFilter(key, value) {
    filters[key] = value
  }

  function setActiveFolder(folderId) {
    activeFolderId.value = folderId
  }

  function clear() {
    files.value = []
    totalFiles.value = 0
    folders.value = []
    virtualFolders.value = []
    tags.value = []
    quota.value = null
    activeFolderId.value = null
    filters.query = ''
    filters.mime = null
    filters.tagId = null
    filters.source = null
    filters.sort = 'date'
    filters.order = 'desc'
    loadError.value = null
  }

  return {
    files: readonly(files),
    totalFiles: readonly(totalFiles),
    folders: readonly(folders),
    virtualFolders: readonly(virtualFolders),
    tags: readonly(tags),
    quota: readonly(quota),
    activeFolderId: readonly(activeFolderId),
    filters: readonly(filters),
    isLoadingFiles: readonly(isLoadingFiles),
    isLoadingFolders: readonly(isLoadingFolders),
    isLoadingTags: readonly(isLoadingTags),
    isLoadingQuota: readonly(isLoadingQuota),
    loadError: readonly(loadError),
    fetchFiles,
    fetchFolders,
    fetchTags,
    fetchQuota,
    setFilter,
    setActiveFolder,
    clear
  }
})
