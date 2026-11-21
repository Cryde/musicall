import { isGuitarProName } from './guitarPro.js'

/**
 * The files collection's `format` value for Guitar Pro files. Mirrors
 * BandSpaceFileCollectionProvider::FORMAT_GUITAR_PRO.
 */
export const GUITAR_PRO_FORMAT = 'guitar_pro'

/** When a tab last changed: a new version, a rename or a move dates the file, an untouched one has only its upload. */
export function tabLastChange(file) {
  return file.update_datetime ?? file.creation_datetime
}

/** The titles of the songs a tab is attached to. */
export function tabSongTitles(file) {
  return (file.attachments ?? [])
    .filter((attachment) => attachment.source_type === 'song')
    .map((attachment) => attachment.source_label)
}

/**
 * The tab already in the space under a picked file's name, so it can take a new version instead of
 * becoming a second file. Case is ignored, like the server's search the candidates come from.
 */
export function tabNamed(tabs, name) {
  const wanted = name.toLowerCase()

  return tabs.find((tab) => tab.original_name.toLowerCase() === wanted) ?? null
}

/** The tab a finished upload opens in the reader: the first Guitar Pro file it stored. */
export function firstUploadedTab(files) {
  return files.find((file) => isGuitarProName(file.original_name)) ?? null
}
