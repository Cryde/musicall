import { intlLocale } from '../i18n/formatLocale.js'

/**
 * The songs a file is attached to, in the order they were attached, named as the listing names them,
 * with the song folder it sits in there (#1451), null at the song's root.
 *
 * @param {{attachments?: ReadonlyArray<{source_type: string, source_id: string, source_label?: string, song_folder_id?: string|null}>}} file
 * @returns {Array<{id: string, title: string, folderId: string|null}>}
 */
export function fileSongs(file) {
  return (file.attachments ?? [])
    .filter((attachment) => attachment.source_type === 'song' && attachment.source_id)
    .map((attachment) => ({
      id: attachment.source_id,
      title: attachment.source_label,
      folderId: attachment.song_folder_id ?? null
    }))
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
 * The songs that have files, from the same listing: the nodes « Titres » unfolds into in Files
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

/**
 * How many files sit directly in each song folder (#1451), from the same listing: the counts the Files
 * tree shows beside a song's folders, as it shows a real folder's.
 *
 * @param {ReadonlyArray<{attachments?: ReadonlyArray<{source_type: string, song_folder_id?: string|null}>}>} files
 * @returns {Map<string, number>} folder id to file count; an empty folder is absent
 */
export function songFolderFileCounts(files) {
  const counts = new Map()
  for (const { folderId } of files.flatMap(fileSongs)) {
    if (folderId) counts.set(folderId, (counts.get(folderId) ?? 0) + 1)
  }
  return counts
}
