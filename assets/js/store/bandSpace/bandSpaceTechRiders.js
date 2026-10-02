import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceTechRidersApi from '../../api/bandSpace/band-space-tech-riders.js'
import { aggregateSaveStatus } from '../../utils/riderWorkspace.js'

export const useBandTechRidersStore = defineStore('bandTechRiders', () => {
  // Two lists rather than one filtered list, because the rider switcher shows live riders
  // and an "Archives" group at the same time. A band has a handful of riders and the
  // collection is unpaginated, so fetching both costs two small requests on module load.
  const liveRiders = ref([])
  const archivedRiders = ref([])
  const activeTechRider = ref(null)
  // Per item: 'pending' (edited, waiting on the debounce), 'saving', 'saved', or 'error' with the
  // server's message. Every editor reports here, autosaving or not, so the page can show one status.
  const itemSaveStates = ref({})
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
  // Per item, not one counter: two items save independently and must not cancel each other.
  const contentRequestIds = new Map()

  const stagePlotIcons = ref([])
  let stagePlotIconsRequest = null

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
   * The copy is always live, even from an archived source, so it joins the live list regardless of
   * which list the original was in.
   */
  async function duplicateTechRider(bandSpaceId, riderId, name = null) {
    const created = await bandSpaceTechRidersApi.duplicateTechRider(bandSpaceId, riderId, name)
    liveRiders.value = [created, ...liveRiders.value]

    return created
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

  /**
   * Guarded per item, the same way reorderItems is, because a contacts item has two writers: the
   * note autosaves on a debounce while the emails switch saves immediately. Both send the whole
   * `content`, so a slow note save landing after a newer toggle would put the old flag back on
   * screen. That is worth guarding for its own sake here, since the flag decides whether four
   * people's addresses appear on a document sent to strangers.
   */
  async function saveItemContent(bandSpaceId, riderId, itemId, content) {
    const requestId = (contentRequestIds.get(itemId) ?? 0) + 1
    contentRequestIds.set(itemId, requestId)

    const updated = await bandSpaceTechRidersApi.updateItem(bandSpaceId, riderId, itemId, {
      content
    })

    // A stale answer is dropped rather than applied. The newer request is already in flight and
    // its answer is the one that matches what the user last asked for.
    if (contentRequestIds.get(itemId) !== requestId) return

    replaceItem(updated)
  }

  async function setItemFile(bandSpaceId, riderId, itemId, fileId) {
    replaceItem(
      await bandSpaceTechRidersApi.updateItem(bandSpaceId, riderId, itemId, { file_id: fileId })
    )
  }

  async function savePatchList(bandSpaceId, riderId, itemId, grid) {
    replaceItem(await bandSpaceTechRidersApi.savePatchList(bandSpaceId, riderId, itemId, grid))
  }

  async function saveStagePlot(bandSpaceId, riderId, itemId, plot) {
    replaceItem(await bandSpaceTechRidersApi.saveStagePlot(bandSpaceId, riderId, itemId, plot))
  }

  /**
   * The icon catalogue is static application data, so it is fetched once per session and shared
   * by every plot editor on the page. Concurrent callers await the same promise rather than each
   * firing a request.
   */
  async function loadStagePlotIcons() {
    if (stagePlotIcons.value.length > 0) return stagePlotIcons.value
    stagePlotIconsRequest ??= bandSpaceTechRidersApi
      .getStagePlotIcons()
      .then((icons) => {
        stagePlotIcons.value = icons
        return icons
      })
      .catch((error) => {
        // Cleared so a failed load can be retried rather than caching the rejection forever.
        stagePlotIconsRequest = null
        throw error
      })

    return stagePlotIconsRequest
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
    // Whatever it was waiting on or refused is gone with it, and would otherwise hold the header on
    // « Erreur » and keep the leave-page prompt firing for a section that no longer exists.
    clearItemSaveState(itemId)
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

  /**
   * `retry` is what the header's « Réessayer » runs for an item in error: the editor that failed
   * knows what to send again, the page does not.
   */
  function setItemSaveState(itemId, state, message = null, retry = null) {
    itemSaveStates.value = { ...itemSaveStates.value, [itemId]: { state, message, retry } }
  }

  /** One status for the whole rider, as the header shows it. */
  const saveStatus = computed(() =>
    aggregateSaveStatus(Object.values(itemSaveStates.value).map(({ state }) => state))
  )

  /** Sends again every refused save that can be sent again. */
  function retryRefusedSaves() {
    for (const { state, retry } of Object.values(itemSaveStates.value)) {
      if (state === 'error' && retry) retry()
    }
  }

  function clearItemSaveState(itemId) {
    if (!(itemId in itemSaveStates.value)) return
    const { [itemId]: _cleared, ...rest } = itemSaveStates.value
    itemSaveStates.value = rest
  }

  /** Every editor starts from the server's state, so states left by a previous visit mean nothing. */
  function forgetSaveStates() {
    itemSaveStates.value = {}
  }

  function saveStateFor(itemId) {
    return itemSaveStates.value[itemId] ?? null
  }

  /** Anything the server has not confirmed yet: what a closed tab or an F5 would take with it. */
  const hasUnconfirmedEdits = computed(() =>
    Object.values(itemSaveStates.value).some(({ state }) => state !== 'saved')
  )

  /**
   * Edits no save will ever carry: refused by the server, or failing a check the editor runs first.
   * Leaving the page, or switching rider, unmounts every editor, and each one flushes what is still
   * waiting on its debounce on the way out. These are the only edits that are actually lost.
   */
  const refusedItemIds = computed(() =>
    Object.entries(itemSaveStates.value)
      .filter(([, { state }]) => state === 'error')
      .map(([itemId]) => itemId)
  )

  /**
   * Asks once, and only when there is something to lose. Returns true when it is safe to carry
   * on, so callers read as `if (!confirmDiscardingEdits()) return`.
   */
  function confirmDiscardingEdits() {
    if (refusedItemIds.value.length === 0) return true

    const confirmed = window.confirm(
      "Des modifications n'ont pas pu être enregistrées et seront perdues. Continuer ?"
    )
    if (confirmed) {
      itemSaveStates.value = {}
    }

    return confirmed
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
    contentRequestIds.clear()
    // The editors holding these edits are about to be destroyed, so keeping their states would
    // leave the guard warning about changes that no longer exist anywhere.
    itemSaveStates.value = {}
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
    hasUnconfirmedEdits,
    saveStatus,
    refusedItemIds,
    stagePlotIcons: readonly(stagePlotIcons),
    isLoading: readonly(isLoading),
    isLoadingActive: readonly(isLoadingActive),
    loadError: readonly(loadError),
    fetchRiders,
    fetchActive,
    createTechRider,
    renameTechRider,
    archiveTechRider,
    unarchiveTechRider,
    duplicateTechRider,
    createItem,
    renameItem,
    saveItemContent,
    savePatchList,
    saveStagePlot,
    loadStagePlotIcons,
    setItemFile,
    setItemIncluded,
    setItemSaveState,
    retryRefusedSaves,
    clearItemSaveState,
    forgetSaveStates,
    saveStateFor,
    confirmDiscardingEdits,
    deleteItem,
    reorderItems,
    findRider,
    clearActive,
    clear
  }
})
