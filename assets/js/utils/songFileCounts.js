import { intlLocale } from '../i18n/formatLocale.js'

/**
 * The songs a file is attached to, in the order they were attached, named as the listing names them.
 *
 * @param {{attachments?: ReadonlyArray<{source_type: string, source_id: string, source_label?: string}>}} file
 * @returns {Array<{id: string, title: string}>}
 */
export function fileSongs(file) {
  return (file.attachments ?? [])
    .filter((attachment) => attachment.source_type === 'song' && attachment.source_id)
    .map((attachment) => ({ id: attachment.source_id, title: attachment.source_label }))
}

/**
 * How many files each song has attached, from one listing of the files attached to songs, so a
 * repertoire or a setlist never asks once per song.
 *
 * @param {ReadonlyArray<{attachments?: ReadonlyArray<{source_type: string, source_id: string}>}>} files
 * @returns {Map<string, number>} song id to file count; a song with none is absent
 */
export function songFileCounts(files) {
  const counts = new Map()
  for (const { id } of files.flatMap(fileSongs)) {
    counts.set(id, (counts.get(id) ?? 0) + 1)
  }
  return counts
}

/**
 * The songs that have files, from the same listing: the nodes « Chansons » unfolds into in Files
 * (#1431). Sorted by title the way the répertoire sorts, so a song is found in the same place in both.
 *
 * @param {ReadonlyArray<{attachments?: ReadonlyArray<{source_type: string, source_id: string, source_label: string}>}>} files
 * @returns {Array<{id: string, title: string, fileCount: number}>}
 */
export function songsWithFiles(files) {
  const titles = new Map(files.flatMap(fileSongs).map((song) => [song.id, song.title]))
  const counts = songFileCounts(files)

  return [...titles]
    .map(([id, title]) => ({ id, title, fileCount: counts.get(id) }))
    .sort((a, b) => a.title.localeCompare(b.title, intlLocale()))
}
