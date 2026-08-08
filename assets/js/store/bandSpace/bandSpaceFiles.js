import { defineStore } from 'pinia'
import { computed, reactive, readonly, ref } from 'vue'
import bandSpaceFilesApi from '../../api/bandSpace/band-space-files.js'
import {
  FILES_PAGE_SIZE,
  fileCountLabel,
  hasMoreToLoad,
  mergePage,
  nextPageToLoad,
  queryKeyOf
} from '../../utils/filePagination.js'

export const TRASH_FOLDER_ID = 'trash'

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
  // The trash is selected through activeFolderId, like the virtual source folders, so the whole existing
  // selection and fetch path is reused. This count feeds the sidebar badge even when the trash is closed.
  const archivedCount = ref(0)

  const filters = reactive({
    query: '',
    mime: null,
    tagId: null,
    source: null,
    sort: 'date',
    order: 'desc'
  })

  const isLoadingFiles = ref(false)
  // A filter change on a list that already has rows swaps its content with no skeleton, so this
  // drives a visible refreshing state instead of the content changing under the user in silence.
  const isRefreshingFiles = ref(false)
  const isLoadingMoreFiles = ref(false)
  // Fingerprint of the query the loaded pages belong to, so a page resolving after the filters
  // moved is recognised as continuing a list that is no longer on screen.
  const loadedQueryKey = ref(null)
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
  // Kept apart from loadError, which replaces the whole panel: a further page failing must not take
  // away the rows the user is already reading.
  const loadMoreError = ref(null)
  const activeFileError = ref(null)

  let filesRequestId = 0
  let foldersRequestId = 0
  let activeFileRequestId = 0
  let activitiesRequestId = 0

  // Served by the quota endpoint so the interface quotes the same window the purge enforces.
  const trashRetentionDays = computed(() => quota.value?.trash_retention_days ?? 30)

  const hasMoreFiles = computed(() => hasMoreToLoad(files.value.length, totalFiles.value))
  const filesCountLabel = computed(() => fileCountLabel(files.value.length, totalFiles.value))

  const activeFile = computed(() => {
    if (!activeFileId.value) return null
    return activeFileFull.value && activeFileFull.value.id === activeFileId.value
      ? activeFileFull.value
      : files.value.find((f) => f.id === activeFileId.value) || null
  })

  /** Loads the first page, replacing whatever was on screen. Every filter change comes through here. */
  async function fetchFiles(bandSpaceId) {
    const requestId = ++filesRequestId
    isLoadingFiles.value = files.value.length === 0
    isRefreshingFiles.value = true
    loadError.value = null
    loadMoreError.value = null

    const params = buildFileParams(1)

    try {
      const result = await bandSpaceFilesApi.getFiles(bandSpaceId, params)
      if (requestId !== filesRequestId) return
      files.value = result.member ?? []
      totalFiles.value = result.totalItems ?? 0
      loadedQueryKey.value = queryKeyOf(params)
    } catch (e) {
      if (requestId !== filesRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === filesRequestId) {
        isLoadingFiles.value = false
        isRefreshingFiles.value = false
      }
    }
  }

  /**
   * Appends the next page. The page number comes from the rows already held rather than a counter,
   * so a file deleted, restored or moved out of the list since the last page cannot open a gap the
   * next request steps over. See utils/filePagination.js.
   */
  async function fetchMoreFiles(bandSpaceId) {
    if (isLoadingMoreFiles.value || isRefreshingFiles.value || !hasMoreFiles.value) return

    const requestId = ++filesRequestId
    const params = buildFileParams(nextPageToLoad(files.value.length, FILES_PAGE_SIZE))
    const requestedQueryKey = queryKeyOf(params)
    isLoadingMoreFiles.value = true
    loadMoreError.value = null

    try {
      const result = await bandSpaceFilesApi.getFiles(bandSpaceId, params)
      if (requestId !== filesRequestId || requestedQueryKey !== loadedQueryKey.value) return
      const heldBefore = files.value.length
      files.value = mergePage(files.value, result.member ?? [])
      totalFiles.value = result.totalItems ?? totalFiles.value

      // A page that brings nothing new while the server still reports more is the signature of a row
      // that somebody else slipped in behind the window. The page number is derived from how many
      // rows are held, so it stops moving too, and every further click re-requests this same offset
      // for good. Rebuilding from the first page is the only thing that can reach the new row.
      if (files.value.length === heldBefore && hasMoreFiles.value) {
        isLoadingMoreFiles.value = false
        await fetchFiles(bandSpaceId)

        return
      }
    } catch (e) {
      if (requestId !== filesRequestId) return
      loadMoreError.value = e.message
    } finally {
      // Cleared unconditionally: only one further page is ever in flight, and a filter change
      // superseding this one must not leave the button spinning for good.
      isLoadingMoreFiles.value = false
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

  function buildFileParams(page) {
    // The trash ignores every filter, before they are even read. Its filter bar is hidden, so a search
    // or tag left over from browsing a folder would silently narrow the trash with no visible control to
    // clear it, and someone hunting for the file they just deleted would find it apparently missing.
    // It pages exactly like the live listing does: without that, a trashed file past the fiftieth is
    // unrestorable and app:band-space:purge eventually destroys it.
    if (activeFolderId.value === TRASH_FOLDER_ID) {
      return { archived: true, page, itemsPerPage: FILES_PAGE_SIZE }
    }

    const params = { page, itemsPerPage: FILES_PAGE_SIZE }
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

  /**
   * A file changed folder, from the move dialog or from a drag and drop. Patching the loaded rows
   * rather than refetching keeps the pages the user already loaded: a move made after paging deep
   * into the list would otherwise snap it back to the first page.
   *
   * The root and the virtual source folders list the whole space whatever folder a file sits in, so
   * only a real folder view has to drop the row, and only when the file landed somewhere else.
   */
  function applyFileMoved(fileId, targetFolderId) {
    const isRealFolderView =
      typeof activeFolderId.value === 'string' &&
      activeFolderId.value !== TRASH_FOLDER_ID &&
      !activeFolderId.value.startsWith('virtual:')

    if (isRealFolderView && activeFolderId.value !== targetFolderId) {
      files.value = files.value.filter((f) => f.id !== fileId)
      totalFiles.value = Math.max(0, totalFiles.value - 1)
      return
    }

    files.value = files.value.map((f) =>
      f.id === fileId ? { ...f, folder_id: targetFolderId } : f
    )
  }

  async function fetchArchivedCount(bandSpaceId) {
    const result = await bandSpaceFilesApi.getFiles(bandSpaceId, {
      archived: true,
      itemsPerPage: 1
    })
    archivedCount.value = result.totalItems ?? 0
  }

  async function restoreFile(bandSpaceId, fileId) {
    await bandSpaceFilesApi.restoreFile(bandSpaceId, fileId)
    removeFromTrash(fileId)
    // Restoring puts the file's bytes back into the quota, so the indicator has to catch up.
    fetchQuota(bandSpaceId)
  }

  async function permanentDeleteFile(bandSpaceId, fileId) {
    await bandSpaceFilesApi.permanentDeleteFile(bandSpaceId, fileId)
    removeFromTrash(fileId)
  }

  function removeFromTrash(fileId) {
    files.value = files.value.filter((f) => f.id !== fileId)
    totalFiles.value = Math.max(0, totalFiles.value - 1)
    archivedCount.value = Math.max(0, archivedCount.value - 1)
    if (activeFileId.value === fileId) {
      activeFileId.value = null
      activeFileFull.value = null
    }
  }

  async function deleteFile(bandSpaceId, fileId) {
    isDeletingFile.value = true
    try {
      await bandSpaceFilesApi.deleteFile(bandSpaceId, fileId)
      files.value = files.value.filter((f) => f.id !== fileId)
      totalFiles.value = Math.max(0, totalFiles.value - 1)
      // Deleting only archives, so the file has moved to the trash rather than gone.
      archivedCount.value += 1
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
    loadedQueryKey.value = null
    isRefreshingFiles.value = false
    isLoadingMoreFiles.value = false
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
    loadMoreError.value = null
    activeFileError.value = null
  }

  return {
    files: readonly(files),
    totalFiles: readonly(totalFiles),
    hasMoreFiles,
    filesCountLabel,
    archivedCount: readonly(archivedCount),
    trashRetentionDays,
    folders: readonly(folders),
    virtualFolders: readonly(virtualFolders),
    tags: readonly(tags),
    quota: readonly(quota),
    activeFolderId: readonly(activeFolderId),
    filters: readonly(filters),
    isLoadingFiles: readonly(isLoadingFiles),
    isRefreshingFiles: readonly(isRefreshingFiles),
    isLoadingMoreFiles: readonly(isLoadingMoreFiles),
    isLoadingFolders: readonly(isLoadingFolders),
    isLoadingTags: readonly(isLoadingTags),
    isLoadingQuota: readonly(isLoadingQuota),
    loadError: readonly(loadError),
    loadMoreError: readonly(loadMoreError),
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
    fetchMoreFiles,
    fetchFolders,
    fetchTags,
    fetchQuota,
    fetchFileById,
    fetchFileActivities,
    updateFile,
    applyFileMoved,
    deleteFile,
    fetchArchivedCount,
    restoreFile,
    permanentDeleteFile,
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
