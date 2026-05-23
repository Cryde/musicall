import { defineStore } from 'pinia'
import { computed, reactive, readonly, ref } from 'vue'
import bandSpaceFilesApi from '../../api/bandSpace/band-space-files.js'

export const useBandFilesStore = defineStore('bandFiles', () => {
  const files = ref([])
  const totalFiles = ref(0)
  const folders = ref([])
  const virtualFolders = ref([])
  const tags = ref([])
  const quota = ref(null)
  const activeFolderId = ref(null)
  const activeFileId = ref(null)
  const activeFileFull = ref(null)
  const fileActivities = ref([])
  const shares = ref([])
  const versions = ref([])
  // Drag-and-drop source. { type: 'folder'|'file', id, parentId, descendantIds: string[] }
  const dragSource = ref(null)

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
  const isLoadingActiveFile = ref(false)
  const isLoadingActivities = ref(false)
  const isSavingFile = ref(false)
  const isDeletingFile = ref(false)
  const isLoadingShares = ref(false)
  const isCreatingShare = ref(false)
  const isLoadingVersions = ref(false)
  const isUploadingVersion = ref(false)
  const isRollingBack = ref(false)
  const loadError = ref(null)
  const activeFileError = ref(null)

  let filesRequestId = 0
  let foldersRequestId = 0
  let activeFileRequestId = 0
  let activitiesRequestId = 0

  const activeFile = computed(() => {
    if (!activeFileId.value) return null
    return activeFileFull.value && activeFileFull.value.id === activeFileId.value
      ? activeFileFull.value
      : files.value.find((f) => f.id === activeFileId.value) || null
  })

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
    } else if (activeFolderId.value === 'virtual:note') {
      params.source = 'note'
    } else if (activeFolderId.value === 'virtual:song') {
      params.source = 'song'
    } else if (activeFolderId.value === 'virtual:setlist') {
      params.source = 'setlist'
    } else if (activeFolderId.value) {
      params.folderId = activeFolderId.value
    } else if (filters.source) {
      params.source = filters.source
    }

    return params
  }

  async function createFolder(bandSpaceId, data) {
    await bandSpaceFilesApi.createFolder(bandSpaceId, data)
    await fetchFolders(bandSpaceId)
  }

  async function updateFolder(bandSpaceId, folderId, data) {
    await bandSpaceFilesApi.updateFolder(bandSpaceId, folderId, data)
    await fetchFolders(bandSpaceId)
  }

  async function deleteFolder(bandSpaceId, folderId, options = {}) {
    await bandSpaceFilesApi.deleteFolder(bandSpaceId, folderId, options)
    await fetchFolders(bandSpaceId)
    if (activeFolderId.value === folderId) {
      activeFolderId.value = null
      fetchFiles(bandSpaceId)
    }
    fetchQuota(bandSpaceId)
  }

  async function fetchFileById(bandSpaceId, fileId) {
    const requestId = ++activeFileRequestId
    isLoadingActiveFile.value = true
    activeFileError.value = null
    try {
      const fetched = await bandSpaceFilesApi.getFile(bandSpaceId, fileId)
      if (requestId !== activeFileRequestId) return
      activeFileFull.value = fetched
      const idx = files.value.findIndex((f) => f.id === fetched.id)
      if (idx !== -1) {
        files.value = files.value.map((f) => (f.id === fetched.id ? fetched : f))
      }
    } catch (e) {
      if (requestId !== activeFileRequestId) return
      activeFileError.value = e.status === 404 ? 'Fichier introuvable' : e.message
    } finally {
      if (requestId === activeFileRequestId) {
        isLoadingActiveFile.value = false
      }
    }
  }

  async function fetchFileActivities(bandSpaceId, fileId) {
    const requestId = ++activitiesRequestId
    isLoadingActivities.value = true
    try {
      const result = await bandSpaceFilesApi.getFileActivities(bandSpaceId, fileId)
      if (requestId !== activitiesRequestId) return
      fileActivities.value = result
    } catch {
      // silently fail
    } finally {
      if (requestId === activitiesRequestId) {
        isLoadingActivities.value = false
      }
    }
  }

  async function updateFile(bandSpaceId, fileId, data) {
    isSavingFile.value = true
    try {
      const updated = await bandSpaceFilesApi.updateFile(bandSpaceId, fileId, data)
      files.value = files.value.map((f) => (f.id === fileId ? updated : f))
      if (activeFileFull.value && activeFileFull.value.id === fileId) {
        activeFileFull.value = updated
      }
      return updated
    } finally {
      isSavingFile.value = false
    }
  }

  async function deleteFile(bandSpaceId, fileId) {
    isDeletingFile.value = true
    try {
      await bandSpaceFilesApi.deleteFile(bandSpaceId, fileId)
      files.value = files.value.filter((f) => f.id !== fileId)
      totalFiles.value = Math.max(0, totalFiles.value - 1)
      if (activeFileId.value === fileId) {
        activeFileId.value = null
        activeFileFull.value = null
      }
      fetchQuota(bandSpaceId)
    } finally {
      isDeletingFile.value = false
    }
  }

  function setActiveFile(fileId) {
    activeFileId.value = fileId || null
    if (!fileId) {
      activeFileFull.value = null
      fileActivities.value = []
      activeFileError.value = null
      isLoadingActiveFile.value = false
    }
  }

  async function fetchShares(bandSpaceId) {
    isLoadingShares.value = true
    try {
      shares.value = await bandSpaceFilesApi.getShares(bandSpaceId)
    } catch {
      // silently fail
    } finally {
      isLoadingShares.value = false
    }
  }

  async function createShare(bandSpaceId, fileId, data) {
    isCreatingShare.value = true
    try {
      const created = await bandSpaceFilesApi.createShare(bandSpaceId, fileId, data)
      // The list endpoint returns full BandSpaceFileShareResource entries; refetch
      // to get the canonical row, but return the one-shot created payload to the caller.
      fetchShares(bandSpaceId)
      return created
    } finally {
      isCreatingShare.value = false
    }
  }

  async function revokeShare(bandSpaceId, shareId) {
    await bandSpaceFilesApi.revokeShare(bandSpaceId, shareId)
    shares.value = shares.value.filter((s) => s.id !== shareId)
  }

  async function fetchVersions(bandSpaceId, fileId) {
    isLoadingVersions.value = true
    try {
      versions.value = await bandSpaceFilesApi.getVersions(bandSpaceId, fileId)
    } catch {
      versions.value = []
    } finally {
      isLoadingVersions.value = false
    }
  }

  async function uploadVersion(bandSpaceId, fileId, file, onProgress) {
    isUploadingVersion.value = true
    try {
      const result = await bandSpaceFilesApi.uploadVersion(bandSpaceId, fileId, file, onProgress)
      // Refresh derived state
      fetchQuota(bandSpaceId)
      fetchVersions(bandSpaceId, fileId)
      fetchFileById(bandSpaceId, fileId)
      fetchFileActivities(bandSpaceId, fileId)
      return result
    } finally {
      isUploadingVersion.value = false
    }
  }

  async function rollbackVersion(bandSpaceId, fileId, versionNumber) {
    isRollingBack.value = true
    try {
      const updated = await bandSpaceFilesApi.rollbackVersion(bandSpaceId, fileId, versionNumber)
      files.value = files.value.map((f) => (f.id === fileId ? updated : f))
      if (activeFileFull.value && activeFileFull.value.id === fileId) {
        activeFileFull.value = updated
      }
      fetchVersions(bandSpaceId, fileId)
      fetchFileActivities(bandSpaceId, fileId)
      return updated
    } finally {
      isRollingBack.value = false
    }
  }

  async function uploadFile(bandSpaceId, payload, onProgress) {
    const result = await bandSpaceFilesApi.uploadFile(bandSpaceId, payload, onProgress)
    files.value = [result.file, ...files.value]
    totalFiles.value = totalFiles.value + 1
    fetchQuota(bandSpaceId)
    return result
  }

  async function createTag(bandSpaceId, data) {
    const created = await bandSpaceFilesApi.createTag(bandSpaceId, data)
    tags.value = [...tags.value, created]
    return created
  }

  function setFilter(key, value) {
    filters[key] = value
  }

  function setActiveFolder(folderId) {
    activeFolderId.value = folderId
  }

  function startDrag(source) {
    dragSource.value = source
  }

  function endDrag() {
    dragSource.value = null
  }

  function clear() {
    files.value = []
    totalFiles.value = 0
    folders.value = []
    virtualFolders.value = []
    tags.value = []
    quota.value = null
    activeFolderId.value = null
    activeFileId.value = null
    activeFileFull.value = null
    fileActivities.value = []
    shares.value = []
    versions.value = []
    filters.query = ''
    filters.mime = null
    filters.tagId = null
    filters.source = null
    filters.sort = 'date'
    filters.order = 'desc'
    loadError.value = null
    activeFileError.value = null
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
    activeFileId: readonly(activeFileId),
    activeFile,
    fileActivities: readonly(fileActivities),
    shares: readonly(shares),
    isLoadingShares: readonly(isLoadingShares),
    isCreatingShare: readonly(isCreatingShare),
    versions: readonly(versions),
    isLoadingVersions: readonly(isLoadingVersions),
    isUploadingVersion: readonly(isUploadingVersion),
    isRollingBack: readonly(isRollingBack),
    dragSource: readonly(dragSource),
    startDrag,
    endDrag,
    isLoadingActiveFile: readonly(isLoadingActiveFile),
    isLoadingActivities: readonly(isLoadingActivities),
    isSavingFile: readonly(isSavingFile),
    isDeletingFile: readonly(isDeletingFile),
    activeFileError: readonly(activeFileError),
    fetchFiles,
    fetchFolders,
    fetchTags,
    fetchQuota,
    fetchFileById,
    fetchFileActivities,
    updateFile,
    deleteFile,
    setActiveFile,
    uploadFile,
    createTag,
    fetchShares,
    createShare,
    revokeShare,
    fetchVersions,
    uploadVersion,
    rollbackVersion,
    createFolder,
    updateFolder,
    deleteFolder,
    setFilter,
    setActiveFolder,
    clear
  }
})
