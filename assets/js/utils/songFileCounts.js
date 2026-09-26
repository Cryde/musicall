/**
 * How many files each song has attached, from one listing of the files attached to songs, so a
 * repertoire or a setlist never asks once per song.
 *
 * @param {ReadonlyArray<{attachments?: ReadonlyArray<{source_type: string, source_id: string}>}>} files
 * @returns {Map<string, number>} song id to file count; a song with none is absent
 */
export function songFileCounts(files) {
  const counts = new Map()
  for (const file of files) {
    for (const attachment of file.attachments ?? []) {
      if (attachment.source_type === 'song' && attachment.source_id) {
        counts.set(attachment.source_id, (counts.get(attachment.source_id) ?? 0) + 1)
      }
    }
  }
  return counts
}
