import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceSetlistsApi from '../../api/bandSpace/band-space-setlists.js'

export const useBandSetlistsStore = defineStore('bandSetlists', () => {
  const setlists = ref([])
  // Two lists rather than one filtered list, because the sidebar shows the live setlists and the
  // trash count side by side. A band has a handful of archived setlists and the collection is
  // unpaginated, so this costs one small request on module load.
  const archivedSetlists = ref([])
  const activeSetlist = ref(null)
  const isLoading = ref(false)
  const isLoadingArchived = ref(false)
  const isLoadingActive = ref(false)
  const loadError = ref(null)

  let setlistsRequestId = 0
  let archivedRequestId = 0
  let activeRequestId = 0

  // Both collections come back newest first, so a row moved between them locally has to be placed
  // rather than pushed: restoring a setlist from two years ago must not park it above this month's.
  function byCreationDesc(a, b) {
    return new Date(b.creation_datetime) - new Date(a.creation_datetime)
  }

  async function fetchSetlists(bandSpaceId) {
    const requestId = ++setlistsRequestId
    isLoading.value = setlists.value.length === 0
    loadError.value = null

    try {
      const result = await bandSpaceSetlistsApi.getSetlists(bandSpaceId)
      if (requestId !== setlistsRequestId) return
      setlists.value = result
    } catch (e) {
      if (requestId !== setlistsRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === setlistsRequestId) {
        isLoading.value = false
      }
    }
  }

  async function fetchArchivedSetlists(bandSpaceId) {
    const requestId = ++archivedRequestId
    isLoadingArchived.value = archivedSetlists.value.length === 0

    try {
      const result = await bandSpaceSetlistsApi.getSetlists(bandSpaceId, { archived: true })
      if (requestId !== archivedRequestId) return
      archivedSetlists.value = result
    } catch (e) {
      if (requestId !== archivedRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === archivedRequestId) {
        isLoadingArchived.value = false
      }
    }
  }

  async function fetchActive(bandSpaceId, setlistId) {
    const requestId = ++activeRequestId
    isLoadingActive.value = true
    try {
      const result = await bandSpaceSetlistsApi.getSetlist(bandSpaceId, setlistId)
      if (requestId !== activeRequestId) return
      activeSetlist.value = result
    } catch (e) {
      if (requestId !== activeRequestId) return
      loadError.value = e.message
    } finally {
      if (requestId === activeRequestId) {
        isLoadingActive.value = false
      }
    }
  }

  async function createSetlist(bandSpaceId, data) {
    const created = await bandSpaceSetlistsApi.createSetlist(bandSpaceId, data)
    setlists.value = [created, ...setlists.value]
    return created
  }

  async function renameSetlist(bandSpaceId, setlistId, name) {
    const updated = await bandSpaceSetlistsApi.updateSetlist(bandSpaceId, setlistId, { name })
    setlists.value = setlists.value.map((s) => (s.id === setlistId ? updated : s))
    if (activeSetlist.value?.id === setlistId) {
      activeSetlist.value = updated
    }
    return updated
  }

  /**
   * Archiving moves the setlist to the trash rather than destroying it, so both lists are updated:
   * the trash entry is what the sidebar counts and what offers the way back.
   */
  async function archiveSetlist(bandSpaceId, setlistId) {
    await bandSpaceSetlistsApi.deleteSetlist(bandSpaceId, setlistId)
    const archived = setlists.value.find((s) => s.id === setlistId)
    setlists.value = setlists.value.filter((s) => s.id !== setlistId)
    if (archived) {
      archivedSetlists.value = [
        { ...archived, archive_datetime: new Date().toISOString() },
        ...archivedSetlists.value
      ].sort(byCreationDesc)
    }
    if (activeSetlist.value?.id === setlistId) {
      activeSetlist.value = null
    }
  }

  async function restoreSetlist(bandSpaceId, setlistId) {
    const restored = await bandSpaceSetlistsApi.restoreSetlist(bandSpaceId, setlistId)
    archivedSetlists.value = archivedSetlists.value.filter((s) => s.id !== setlistId)
    setlists.value = [restored, ...setlists.value].sort(byCreationDesc)
    return restored
  }

  async function duplicateSetlist(bandSpaceId, setlistId) {
    const copy = await bandSpaceSetlistsApi.duplicateSetlist(bandSpaceId, setlistId)
    setlists.value = [copy, ...setlists.value]
    return copy
  }

  async function reorderItems(bandSpaceId, setlistId, orderedItemIds) {
    // Optimistic: snapshot current items, reorder locally, send request,
    // restore on error.
    if (!activeSetlist.value || activeSetlist.value.id !== setlistId) {
      return
    }
    const snapshot = activeSetlist.value.items
    const byId = new Map(snapshot.map((i) => [i.id, i]))
    const reordered = orderedItemIds
      .map((id, position) => {
        const item = byId.get(id)
        return item ? { ...item, position } : null
      })
      .filter(Boolean)
    activeSetlist.value = { ...activeSetlist.value, items: reordered }

    const positions = orderedItemIds.map((id, position) => ({ id, position }))
    try {
      await bandSpaceSetlistsApi.reorderItems(bandSpaceId, setlistId, positions)
    } catch (e) {
      // Restore on error
      activeSetlist.value = { ...activeSetlist.value, items: snapshot }
      throw e
    }
  }

  // Keep the matching entry in setlists[] in sync with activeSetlist.items so
  // the sidebar count (setlist.items?.length) stays accurate after edits. The
  // collection endpoint returns full items (readableLink: true), so the shape
  // matches one-for-one.
  function syncSetlistsEntry(setlistId) {
    if (!activeSetlist.value || activeSetlist.value.id !== setlistId) return
    const items = activeSetlist.value.items
    setlists.value = setlists.value.map((s) => (s.id === setlistId ? { ...s, items } : s))
  }

  async function addItem(bandSpaceId, setlistId, data) {
    const created = await bandSpaceSetlistsApi.addItem(bandSpaceId, setlistId, data)
    if (activeSetlist.value?.id === setlistId) {
      activeSetlist.value = {
        ...activeSetlist.value,
        items: [...activeSetlist.value.items, created]
      }
      syncSetlistsEntry(setlistId)
    }
    return created
  }

  async function updateItem(bandSpaceId, setlistId, itemId, data) {
    const updated = await bandSpaceSetlistsApi.updateItem(bandSpaceId, setlistId, itemId, data)
    if (activeSetlist.value?.id === setlistId) {
      activeSetlist.value = {
        ...activeSetlist.value,
        items: activeSetlist.value.items.map((i) => (i.id === itemId ? updated : i))
      }
      syncSetlistsEntry(setlistId)
    }
    return updated
  }

  async function removeItem(bandSpaceId, setlistId, itemId) {
    await bandSpaceSetlistsApi.removeItem(bandSpaceId, setlistId, itemId)
    if (activeSetlist.value?.id === setlistId) {
      // Locally re-derive positions for visual consistency (server already
      // collapses positions on its side).
      const filtered = activeSetlist.value.items
        .filter((i) => i.id !== itemId)
        .map((i, idx) => ({ ...i, position: idx }))
      activeSetlist.value = { ...activeSetlist.value, items: filtered }
      syncSetlistsEntry(setlistId)
    }
  }

  function clear() {
    setlists.value = []
    archivedSetlists.value = []
    activeSetlist.value = null
    isLoading.value = false
    isLoadingArchived.value = false
    isLoadingActive.value = false
    loadError.value = null
  }

  return {
    setlists: readonly(setlists),
    archivedSetlists: readonly(archivedSetlists),
    activeSetlist: readonly(activeSetlist),
    isLoading: readonly(isLoading),
    isLoadingArchived: readonly(isLoadingArchived),
    isLoadingActive: readonly(isLoadingActive),
    loadError: readonly(loadError),
    fetchSetlists,
    fetchArchivedSetlists,
    fetchActive,
    createSetlist,
    renameSetlist,
    archiveSetlist,
    restoreSetlist,
    duplicateSetlist,
    reorderItems,
    addItem,
    updateItem,
    removeItem,
    clear
  }
})
