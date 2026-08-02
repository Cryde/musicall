import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import bandSpaceTechRidersApi from '../../api/bandSpace/band-space-tech-riders.js'

export const useBandTechRidersStore = defineStore('bandTechRiders', () => {
  // Two lists rather than one filtered list, because the rider switcher shows live riders
  // and an "Archives" group at the same time. A band has a handful of riders and the
  // collection is unpaginated, so fetching both costs two small requests on module load.
  const liveRiders = ref([])
  const archivedRiders = ref([])
  const activeTechRider = ref(null)
  const isLoading = ref(false)
  const isLoadingActive = ref(false)
  const loadError = ref(null)

  // Which space the riders belong to, so a fetch can tell "same space, refresh" from
  // "different space, the data on hand is somebody else's".
  const loadedBandSpaceId = ref(null)

  // Guards against a slow response landing after a newer one and overwriting it.
  let listRequestId = 0
  let activeRequestId = 0
  let reorderRequestId = 0

  async function fetchRiders(bandSpaceId) {
    if (bandSpaceId !== loadedBandSpaceId.value) {
      liveRiders.value = []
      archivedRiders.value = []
      loadedBandSpaceId.value = bandSpaceId
    }

    const requestId = ++listRequestId
    isLoading.value = liveRiders.value.length === 0 && archivedRiders.value.length === 0
    loadError.value = null

    try {
      const [live, archived] = await Promise.all([
        bandSpaceTechRidersApi.getTechRiders(bandSpaceId, { archived: false }),
        bandSpaceTechRidersApi.getTechRiders(bandSpaceId, { archived: true })
      ])
      if (requestId !== listRequestId) return
      liveRiders.value = live
      archivedRiders.value = archived
    } catch (e) {
      if (requestId !== listRequestId) return
      loadError.value = e.message
      liveRiders.value = []
      archivedRiders.value = []
    } finally {
      if (requestId === listRequestId) {
        isLoading.value = false
      }
    }
  }

  async function fetchActive(bandSpaceId, riderId) {
    const requestId = ++activeRequestId
    isLoadingActive.value = true
    loadError.value = null

    try {
      const result = await bandSpaceTechRidersApi.getTechRider(bandSpaceId, riderId)
      if (requestId !== activeRequestId) return
      activeTechRider.value = result
    } catch (e) {
      if (requestId !== activeRequestId) return
      loadError.value = e.message
      activeTechRider.value = null
    } finally {
      if (requestId === activeRequestId) {
        isLoadingActive.value = false
      }
    }
  }

  async function createTechRider(bandSpaceId, name) {
    const created = await bandSpaceTechRidersApi.createTechRider(bandSpaceId, { name })
    liveRiders.value = [created, ...liveRiders.value]
    return created
  }

  async function renameTechRider(bandSpaceId, riderId, name) {
    const updated = await bandSpaceTechRidersApi.updateTechRider(bandSpaceId, riderId, { name })
    const replace = (rider) => (rider.id === riderId ? updated : rider)
    liveRiders.value = liveRiders.value.map(replace)
    archivedRiders.value = archivedRiders.value.map(replace)
    if (activeTechRider.value?.id === riderId) {
      activeTechRider.value = updated
    }
    return updated
  }

  /**
   * Archiving and restoring move the rider between the two lists. The open rider is kept
   * open either way: the view shows the archived state and offers the way back, so
   * dropping it would hide the result of the action just taken.
   */
  async function archiveTechRider(bandSpaceId, riderId) {
    await bandSpaceTechRidersApi.archiveTechRider(bandSpaceId, riderId)
    const archived = liveRiders.value.find((rider) => rider.id === riderId)
    liveRiders.value = liveRiders.value.filter((rider) => rider.id !== riderId)
    if (archived) {
      archivedRiders.value = [
        { ...archived, archive_datetime: new Date().toISOString() },
        ...archivedRiders.value
      ]
    }
    if (activeTechRider.value?.id === riderId) {
      activeTechRider.value = {
        ...activeTechRider.value,
        archive_datetime: new Date().toISOString()
      }
    }
  }

  async function unarchiveTechRider(bandSpaceId, riderId) {
    const restored = await bandSpaceTechRidersApi.unarchiveTechRider(bandSpaceId, riderId)
    archivedRiders.value = archivedRiders.value.filter((rider) => rider.id !== riderId)
    liveRiders.value = [restored, ...liveRiders.value]
    if (activeTechRider.value?.id === riderId) {
      activeTechRider.value = restored
    }
    return restored
  }

  /**
   * Items live on activeTechRider and every mutation returns the updated rider, so the
   * open document stays the single source the editor renders from.
   */
  function replaceItem(updated) {
    if (!activeTechRider.value) return
    activeTechRider.value = {
      ...activeTechRider.value,
      items: activeTechRider.value.items.map((item) => (item.id === updated.id ? updated : item))
    }
  }

  async function createItem(bandSpaceId, riderId, { title, type = 'text' }) {
    const created = await bandSpaceTechRidersApi.createItem(bandSpaceId, riderId, { title, type })
    if (activeTechRider.value?.id === riderId) {
      activeTechRider.value = {
        ...activeTechRider.value,
        items: [...activeTechRider.value.items, created],
        item_count: activeTechRider.value.item_count + 1
      }
    }
    return created
  }

  async function renameItem(bandSpaceId, riderId, itemId, title) {
    replaceItem(await bandSpaceTechRidersApi.updateItem(bandSpaceId, riderId, itemId, { title }))
  }

  async function saveItemContent(bandSpaceId, riderId, itemId, content) {
    replaceItem(await bandSpaceTechRidersApi.updateItem(bandSpaceId, riderId, itemId, { content }))
  }

  async function setItemFile(bandSpaceId, riderId, itemId, fileId) {
    replaceItem(
      await bandSpaceTechRidersApi.updateItem(bandSpaceId, riderId, itemId, { file_id: fileId })
    )
  }

  async function setItemIncluded(bandSpaceId, riderId, itemId, isIncluded) {
    replaceItem(
      await bandSpaceTechRidersApi.updateItem(bandSpaceId, riderId, itemId, {
        is_included: isIncluded
      })
    )
  }

  async function deleteItem(bandSpaceId, riderId, itemId) {
    await bandSpaceTechRidersApi.deleteItem(bandSpaceId, riderId, itemId)
    if (activeTechRider.value?.id !== riderId) return
    activeTechRider.value = {
      ...activeTechRider.value,
      items: activeTechRider.value.items.filter((item) => item.id !== itemId),
      item_count: Math.max(0, activeTechRider.value.item_count - 1)
    }
  }

  /**
   * Applies the new order locally first so the list does not jump while the request is in
   * flight, then restores the previous order if the server refuses it.
   */
  async function reorderItems(bandSpaceId, riderId, orderedIds) {
    if (activeTechRider.value?.id !== riderId) return

    // Two quick Monter clicks are two independently valid full-order payloads, so the server
    // keeps whichever lands last while the client shows whichever was sent last. Without this
    // guard those can disagree, silently, until a reload.
    const requestId = ++reorderRequestId
    const previous = activeTechRider.value.items
    const byId = new Map(previous.map((item) => [item.id, item]))
    const reordered = orderedIds
      .map((id, index) => {
        const item = byId.get(id)
        return item ? { ...item, position: index } : null
      })
      .filter(Boolean)

    activeTechRider.value = { ...activeTechRider.value, items: reordered }

    try {
      await bandSpaceTechRidersApi.reorderItems(
        bandSpaceId,
        riderId,
        orderedIds.map((id, index) => ({ id, position: index }))
      )
    } catch (e) {
      // Only the newest attempt may roll back: an older failure must not undo a newer,
      // successful order.
      if (requestId === reorderRequestId) {
        activeTechRider.value = { ...activeTechRider.value, items: previous }
      }
      throw e
    }
  }

  /** Looks in both lists, so a remembered id resolves whether or not it has been archived. */
  function findRider(riderId) {
    return (
      liveRiders.value.find((rider) => rider.id === riderId) ??
      archivedRiders.value.find((rider) => rider.id === riderId) ??
      null
    )
  }

  function clearActive() {
    activeTechRider.value = null
    activeRequestId++
  }

  function clear() {
    liveRiders.value = []
    archivedRiders.value = []
    loadedBandSpaceId.value = null
    loadError.value = null
    listRequestId++
    clearActive()
  }

  return {
    liveRiders: readonly(liveRiders),
    archivedRiders: readonly(archivedRiders),
    activeTechRider: readonly(activeTechRider),
    isLoading: readonly(isLoading),
    isLoadingActive: readonly(isLoadingActive),
    loadError: readonly(loadError),
    fetchRiders,
    fetchActive,
    createTechRider,
    renameTechRider,
    archiveTechRider,
    unarchiveTechRider,
    createItem,
    renameItem,
    saveItemContent,
    setItemFile,
    setItemIncluded,
    deleteItem,
    reorderItems,
    findRider,
    clearActive,
    clear
  }
})
