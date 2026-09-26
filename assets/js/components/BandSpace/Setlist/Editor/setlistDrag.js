/** The drag group shared by the repertoire panel (a source) and the running order (a target). */
export const DRAG_GROUP = 'setlist-programme'

/**
 * What a song dropped on the running order stands in as until the server answers with the real
 * item: shaped like one, so the row renders, and marked pending so it is drawn faded.
 */
export function pendingSongItem(song) {
  return {
    id: `pending-${song.id}-${Date.now()}`,
    type: 'song',
    pending: true,
    label: null,
    duration_override: null,
    note: null,
    transition: null,
    song: {
      id: song.id,
      title: song.title,
      tonality: song.tonality ?? null,
      tempo: song.tempo ?? null,
      reference_duration: song.reference_duration ?? null,
      has_lyrics: song.has_lyrics ?? false,
      archive_datetime: null
    }
  }
}
