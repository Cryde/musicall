import { onBeforeUnmount, watch } from 'vue'
import { useBandTechRidersStore } from '../store/bandSpace/bandSpaceTechRiders.js'
import { createSnapshotAutosave } from '../utils/snapshotAutosave.js'

/**
 * Autosave for the tech rider editors that send their whole state in one request: the patch list
 * and the stage plot (#1089). The loop itself lives in utils/snapshotAutosave.js; this wires it to
 * the component's reactivity and reports its state to the store, so the page shows one status.
 *
 * Both editors used to have an explicit save bar, for two reasons this keeps covered: a drag moves
 * an element dozens of times, so saves are debounced, and a grid is often half filled in, so
 * `validate` can hold a save the server would refuse and point at the field instead.
 *
 * @param {object}   options
 * @param {function} options.itemId      () => string
 * @param {function} options.isReadOnly  () => boolean
 * @param {function} options.serialise   () => string, the state as it would be sent
 * @param {function} options.save        (payload) => Promise
 * @param {function} [options.validate]  () => string|null
 * @param {function} [options.onError]   (error) => string
 * @param {number}   [options.delayMs]
 */
export function useSnapshotAutosave({ itemId, isReadOnly, serialise, delayMs = 1500, ...rest }) {
  const techRidersStore = useBandTechRidersStore()
  let isMounted = true

  // A fresh editor shows the server's state, so whatever an earlier instance left for this item
  // (its last flush failing after it was gone, say) no longer describes anything on screen.
  techRidersStore.clearItemSaveState(itemId())

  const autosave = createSnapshotAutosave({
    ...rest,
    serialise,
    delayMs,
    // An unmounted editor still finishes its last save, but has nobody left to report to: a state
    // written then would make the page warn about edits that are no longer anywhere.
    onState: (state, message = null) => {
      if (isMounted) techRidersStore.setItemSaveState(itemId(), state, message)
    }
  })

  watch(serialise, () => {
    if (!isReadOnly()) autosave.changed()
  })

  // The last edits go out on the way out: leaving the page, or switching rider, unmounts the editor.
  // Reporting stops first, so the flush's own states are not left behind either.
  onBeforeUnmount(() => {
    isMounted = false
    autosave.flush()
    techRidersStore.clearItemSaveState(itemId())
  })

  return {
    markSaved: autosave.markSaved,
    /**
     * Whether a refresh from the server may be applied. Never over an edit still on screen, never
     * while a save is out (the refresh may be its own answer), and never when it only confirms what
     * was saved: rebuilding the rows would hand every input a new key and take the cursor away.
     * A change from elsewhere landing during our own save is therefore skipped; the next refresh of
     * the rider brings it.
     */
    shouldReseed: (serverSnapshot) =>
      !autosave.isDirty() && !autosave.isSaving() && !autosave.isConfirmed(serverSnapshot)
  }
}
