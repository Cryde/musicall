import { isGuitarProName } from './guitarPro.js'

/** What a file's row offers first, by what the file is (#1431). */
export const FILE_ACTIONS = Object.freeze({
  READ: 'read',
  PLAY: 'play',
  VIEW: 'view',
  DOWNLOAD: 'download'
})

const VIEWED_TYPES = ['application/pdf']
const VIEWED_KINDS = ['image/', 'video/']

/**
 * A Guitar Pro file opens in the reader, a sound plays where it is, a PDF, an image or a video opens in
 * the browser, anything else downloads. Guitar Pro is told by its name, as everywhere (#1428), and only
 * a member who may read tabs gets the reader: the others download it.
 *
 * @param {{original_name?: string, mime_type?: ?string}} file
 * @param {{canReadTabs: boolean}} options
 * @returns {string} one of FILE_ACTIONS
 */
export function attachedFileAction(file, { canReadTabs }) {
  if (isGuitarProName(file.original_name)) {
    return canReadTabs ? FILE_ACTIONS.READ : FILE_ACTIONS.DOWNLOAD
  }
  const mime = (file.mime_type ?? '').toLowerCase()
  if (mime.startsWith('audio/')) return FILE_ACTIONS.PLAY
  if (VIEWED_TYPES.includes(mime) || VIEWED_KINDS.some((kind) => mime.startsWith(kind))) {
    return FILE_ACTIONS.VIEW
  }
  return FILE_ACTIONS.DOWNLOAD
}

function lastChange(file) {
  return Date.parse(file.update_datetime ?? file.creation_datetime ?? '') || 0
}

/**
 * The tab a song page draws first: of its Guitar Pro files, the one changed last, which is the one the
 * band is playing these days. The others stay rows like any file.
 *
 * @template {{original_name?: string, update_datetime?: ?string, creation_datetime?: ?string}} F
 * @param {ReadonlyArray<F>} files
 * @returns {F|null}
 */
export function featuredTab(files) {
  let latest = null
  for (const file of files) {
    if (!isGuitarProName(file.original_name)) continue
    if (latest === null || lastChange(file) > lastChange(latest)) latest = file
  }
  return latest
}
