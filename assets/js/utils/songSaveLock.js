/**
 * One field of a song saving at a time. Each PATCH answers with the whole song and the store keeps that
 * copy, so two saves in flight on one song could land out of order and the older copy put a value back.
 * The other fields of that song wait; another song never waits.
 *
 * @param {Map<string, string>} saving song id to the field being saved; a reactive Map in a component
 */
export function createSongSaveLock(saving = new Map()) {
  return {
    /** Whether this field has to wait for another field of the same song. */
    isLocked(songId, field) {
      const busy = saving.get(songId)
      return busy !== undefined && busy !== field
    },
    /** Runs the write as this song's only save; a refusal is rethrown to the field. */
    async run(songId, field, write) {
      saving.set(songId, field)
      try {
        return await write()
      } finally {
        saving.delete(songId)
      }
    }
  }
}
