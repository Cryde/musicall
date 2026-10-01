import { createDebouncedSaver } from './debouncedSaver.js'

/**
 * The autosave loop of the tech rider editors that send their whole state in one request, the patch
 * list and the stage plot (#1089). Pure, with no Vue around it, so the races are tested without a
 * browser: see snapshotAutosave.test.js. useSnapshotAutosave wires it to a component and the store.
 *
 * The state is compared as a serialised snapshot. `changed` is called on every edit; the snapshot
 * recorded as saved is the one the server confirmed, never one read after the round trip.
 *
 * @param {object}   options
 * @param {function} options.serialise  () => string, the state as it would be sent
 * @param {function} options.save       (payload) => Promise
 * @param {function} options.onState    (state, message) => void, state being pending|saving|saved|error
 * @param {function} [options.validate] () => string|null, a reason not to send
 * @param {function} [options.onError]  (error) => string, the message for a refused save
 * @param {number}   options.delayMs
 */
export function createSnapshotAutosave({
  serialise,
  save,
  onState,
  validate = () => null,
  onError = (error) => error.message,
  delayMs
}) {
  let savedSnapshot = serialise()
  let inFlight = false

  const saver = createDebouncedSaver({
    delayMs,
    onSave: async (snapshot) => {
      const problem = validate()
      if (problem) {
        onState('error', problem)
        return
      }

      onState('saving')
      inFlight = true
      try {
        await save(JSON.parse(snapshot))
        savedSnapshot = snapshot
      } catch (error) {
        onState('error', onError(error))
        return
      } finally {
        inFlight = false
      }

      // Typed, or undone, while the save was out: what is on screen now is not what the server
      // holds, and no edit is left to trigger another save, so this one schedules it.
      const current = serialise()
      if (current === savedSnapshot) {
        onState('saved')
      } else {
        onState('pending')
        saver.schedule(current)
      }
    }
  })

  function changed() {
    const snapshot = serialise()
    if (snapshot === savedSnapshot) {
      // Back to what the server holds: nothing to send. While a save is out, its answer decides.
      saver.cancel()
      if (!inFlight) onState('saved')
      return
    }

    onState('pending')
    saver.schedule(snapshot)
  }

  return {
    changed,
    flush: saver.flush,
    /** After reseeding from the server: the state on screen is the confirmed one. */
    markSaved() {
      saver.cancel()
      savedSnapshot = serialise()
    },
    isDirty: () => serialise() !== savedSnapshot,
    /** A save is out, so a refresh arriving now may be its own answer: never reseed over it. */
    isSaving: () => inFlight,
    /** Whether a snapshot from the server only confirms what was last saved. */
    isConfirmed: (snapshot) => snapshot === savedSnapshot
  }
}
