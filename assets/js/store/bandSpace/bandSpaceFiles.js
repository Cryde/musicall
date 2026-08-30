import { defineStore } from 'pinia'
import { computed, reactive, readonly, ref } from 'vue'
import bandSpaceFilesApi from '../../api/bandSpace/band-space-files.js'
import {
  listedFolderId,
  NO_FOLDER_LISTED,
  ROOT_FOLDER_ID
} from '../../constants/folderSelection.js'
import {
  fileListingParams,
  folderPathOf,
  isSearchActive,
  listedFolderOfRows,
  treeHoldsFolder,
  uploadBelongsInListing
} from '../../utils/fileListing.js'
import {
  FILES_PAGE_SIZE,
  fileCountLabel,
  hasMoreToLoad,
  mergePage,
  nextPageToLoad,
  queryKeyOf
} from '../../utils/filePagination.js'
import { prunedSelection } from '../../utils/fileSelection.js'

export const useBandFilesStore = defineStore('bandFiles', () => {
  const files = ref([])
  const totalFiles = ref(0)
  const folders = ref([])
  const virtualFolders = ref([])
  const tags = ref([])
  const quota = ref(null)
  // The root of the tree, not the whole space: a file belongs to one place, so it is listed in one.
  // « Tous les fichiers » is still a selection, it is just no longer where the panel opens.
  const activeFolderId = ref(ROOT_FOLDER_ID)
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
  // Bulk selection. Replaced wholesale rather than mutated, so computeds reading it re-evaluate.
  const selectedFileIds = ref(new Set())
  // The row the last toggle happened on, which a shift-click draws its run from.
  const selectionAnchorId = ref(null)

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
  // The panel's skeleton belongs to the first tree only. Every later refresh flips isLoadingFolders too,
  // and in a space with no folders yet that matched the skeleton's own condition, tearing the whole
  // panel down and remounting it on every file delete, restore or move.
  const hasLoadedFolders = ref(false)
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

  // Same reason, for the per-file upload limit: the dialog refuses an oversize file before sending it,
  // and it has to refuse against the server's real number. The fallback only covers the window before
  // the quota has loaded, and it is deliberately the value the server ships today rather than a
  // rounder guess, so a mismatch shows up as a refusal the server then disagrees with.
  const maxUploadSizeBytes = computed(() => quota.value?.max_upload_size_bytes ?? 500 * 1024 * 1024)

  // Exposed because the panel dresses itself differently while searching: no subfolder rows, and every
  // row says which folder it turned up in, since the results are no longer one place.
  const isSearching = computed(() => isSearchActive(filters))

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

    const params = fileListingParams(activeFolderId.value, filters, 1)

    try {
      const result = await bandSpaceFilesApi.getFiles(bandSpaceId, params)
      if (requestId !== filesRequestId) return
      files.value = result.member ?? []
      totalFiles.value = result.totalItems ?? 0
      loadedQueryKey.value = queryKeyOf(params)
      // A filter change or a refresh after a bulk write changes which rows exist. Acting on an id
      // the member can no longer see is the one outcome worth preventing.
      selectedFileIds.value = new Set(
        prunedSelection(
          [...selectedFileIds.value],
          files.value.map((file) => file.id)
        )
      )
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
    const params = fileListingParams(
      activeFolderId.value,
      filters,
      nextPageToLoad(files.value.length, FILES_PAGE_SIZE)
    )
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
        // Set even when the request failed: a tree that could not be loaded is not a reason to leave the
        // panel behind a skeleton for good.
        hasLoadedFolders.value = true
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
    // The panel may have been inside the deleted folder, or inside one of its subfolders, which the
    // delete_all strategy takes with it too. Asking the refreshed tree covers both, where comparing
    // against the deleted id alone left the panel standing in a folder that no longer exists, listing
    // nothing until something else was clicked. The fallback is the root rather than the flat listing of
    // the whole space, which would answer a deletion with every file there is.
    //
    // Only a real folder can go missing: listedFolderId answers with a string for one, and with the root
    // or NO_FOLDER_LISTED for every selection no folder deletion can invalidate.
    const listedFolder = listedFolderId(activeFolderId.value)
    if (typeof listedFolder === 'string' && !treeHoldsFolder(folders.value, listedFolder)) {
      activeFolderId.value = ROOT_FOLDER_ID
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
      // A folder change is a move, whoever asked for it: the file detail drawer patches the folder from
      // the same place it patches a name. Only applyFileMoved knows the row may not belong to the
      // listing any more, and it is the one that refreshes the tree's counts. A rename or a tag edit
      // moves nothing and needs neither.
      if ('folder_id' in data) {
        applyFileMoved(bandSpaceId, fileId, data.folder_id ?? null)
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
   * A listing showing one place, the root included, has to drop the row when the file landed somewhere
   * else. The flat listing and the virtual source folders hold files whatever folder they sit in, so
   * there the row only has its folder patched.
   *
   * The tree is refetched because a move changes two folder counts, and a count that lags behind the
   * row the member just dragged reads as a bug.
   */
  function applyFileMoved(bandSpaceId, fileId, targetFolderId) {
    const listedFolder = listedFolderOfRows(activeFolderId.value, filters)

    fetchFolders(bandSpaceId)

    if (listedFolder !== NO_FOLDER_LISTED && listedFolder !== targetFolderId) {
      files.value = files.value.filter((f) => f.id !== fileId)
      totalFiles.value = Math.max(0, totalFiles.value - 1)
      return
    }

    // folder_path as well as folder_id: the row's location label and the link under it read the path, so
    // patching only the id left the row naming the folder the file had just left. Read off the tree in
    // hand, which a file move does not change.
    files.value = files.value.map((f) =>
      f.id === fileId
        ? {
            ...f,
            folder_id: targetFolderId,
            folder_path: folderPathOf(folders.value, targetFolderId)
          }
        : f
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
    // Restoring puts the file's bytes back into the quota and the file back into its folder, so the
    // indicator and the folder counts both have to catch up.
    fetchQuota(bandSpaceId)
    fetchFolders(bandSpaceId)
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
      // Out of its folder, so that folder's count is one lower.
      fetchFolders(bandSpaceId)
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

  /**
   * One file. A batch calls this once per file, so each one lands in the list as it arrives and an
   * interrupted batch leaves behind exactly what the server actually took.
   *
   * The row only joins a listing that would hold it. The dialog lets the member change the destination,
   * and a search or a filter can narrow the listing past any one place, so a row prepended anyway would
   * survive exactly until the next fetch and count for one file too many meanwhile.
   */
  async function uploadFile(bandSpaceId, payload, onProgress, signal) {
    const result = await bandSpaceFilesApi.uploadFile(bandSpaceId, payload, onProgress, signal)

    if (uploadBelongsInListing(activeFolderId.value, filters, result.file.folder_id)) {
      files.value = [result.file, ...files.value]
      totalFiles.value = totalFiles.value + 1
    }
    fetchQuota(bandSpaceId)
    return result
  }

  async function createTag(bandSpaceId, data) {
    const created = await bandSpaceFilesApi.createTag(bandSpaceId, data)
    tags.value = [...tags.value, created]
    return created
  }

  function setSelection(ids, anchorId = null) {
    selectedFileIds.value = new Set(ids)
    selectionAnchorId.value = anchorId
  }

  function clearSelection() {
    selectedFileIds.value = new Set()
    selectionAnchorId.value = null
  }

  /**
   * Bulk delete and bulk restore both answer 204, so the rows are pruned locally the way deleteFile
   * and removeFromTrash already do, and the folder counts and quota are refetched.
   */
  /**
   * The ticked ids that are still rows on screen.
   *
   * A single row action prunes files.value without touching the selection: trashing one card from
   * its own kebab menu while three are ticked leaves a fourth id in the Set. Sending it would make
   * the endpoint refuse the whole batch as "introuvable", so the two files the member can see
   * selected would not be deleted either. The bar already displays the selection this way.
   */
  function selectedVisibleIds() {
    return prunedSelection(
      [...selectedFileIds.value],
      files.value.map((file) => file.id)
    )
  }

  async function bulkDeleteFiles(bandSpaceId) {
    const ids = selectedVisibleIds()
    await bandSpaceFilesApi.bulkDeleteFiles(bandSpaceId, ids)
    dropRows(ids)
    archivedCount.value += ids.length
    clearSelection()
    fetchQuota(bandSpaceId)
    fetchFolders(bandSpaceId)
  }

  async function bulkRestoreFiles(bandSpaceId) {
    const ids = selectedVisibleIds()
    await bandSpaceFilesApi.bulkRestoreFiles(bandSpaceId, ids)
    dropRows(ids)
    archivedCount.value = Math.max(0, archivedCount.value - ids.length)
    clearSelection()
    fetchQuota(bandSpaceId)
    fetchFolders(bandSpaceId)
  }

  /**
   * Refetches rather than reconciling row by row: a move can take every file out of the listing at
   * once, and the page window is derived from how many rows are loaded, so pruning a batch spread
   * across pages is the bug filePagination.js exists for.
   */
  async function bulkMoveFiles(bandSpaceId, folderId) {
    await bandSpaceFilesApi.bulkMoveFiles(bandSpaceId, selectedVisibleIds(), folderId)
    clearSelection()
    await fetchFiles(bandSpaceId)
    fetchFolders(bandSpaceId)
  }

  function dropRows(ids) {
    const removed = new Set(ids)
    files.value = files.value.filter((file) => !removed.has(file.id))
    totalFiles.value = Math.max(0, totalFiles.value - ids.length)
    if (removed.has(activeFileId.value)) {
      activeFileId.value = null
      activeFileFull.value = null
    }
  }

  function setFilter(key, value) {
    filters[key] = value
  }

  function setActiveFolder(folderId) {
    activeFolderId.value = folderId
    // The action bar always describes rows the member can see, so a selection never survives a move
    // to another folder or into the trash.
    clearSelection()
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
    hasLoadedFolders.value = false
    isRefreshingFiles.value = false
    isLoadingMoreFiles.value = false
    folders.value = []
    virtualFolders.value = []
    tags.value = []
    quota.value = null
    activeFolderId.value = ROOT_FOLDER_ID
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
    selectedFileIds.value = new Set()
    selectionAnchorId.value = null
  }

  return {
    files: readonly(files),
    totalFiles: readonly(totalFiles),
    hasMoreFiles,
    filesCountLabel,
    archivedCount: readonly(archivedCount),
    trashRetentionDays,
    maxUploadSizeBytes,
    folders: readonly(folders),
    virtualFolders: readonly(virtualFolders),
    tags: readonly(tags),
    quota: readonly(quota),
    activeFolderId: readonly(activeFolderId),
    filters: readonly(filters),
    isSearching,
    isLoadingFiles: readonly(isLoadingFiles),
    isRefreshingFiles: readonly(isRefreshingFiles),
    isLoadingMoreFiles: readonly(isLoadingMoreFiles),
    isLoadingFolders: readonly(isLoadingFolders),
    hasLoadedFolders: readonly(hasLoadedFolders),
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
    selectedFileIds: readonly(selectedFileIds),
    selectionAnchorId: readonly(selectionAnchorId),
    setSelection,
    clearSelection,
    bulkDeleteFiles,
    bulkRestoreFiles,
    bulkMoveFiles,
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
