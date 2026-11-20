/**
 * What the tab reader remembers on the device: the side panel (shown or hidden on a desktop, which
 * tab) and, per file, the tracks shown, so the bassist reopens a song on the bass. The storage is
 * passed in, and a blocked or corrupt one falls back to the defaults.
 */
export const PANEL_TABS = Object.freeze({ STRUCTURE: 'structure', TRACKS: 'tracks' })
export const DEFAULT_PANEL = Object.freeze({ visible: true, tab: PANEL_TABS.STRUCTURE })

const PANEL_KEY = 'musicall.tabs.panel'
const TRACKS_KEY = 'musicall.tabs.tracks'
/** Files remembered, most recent first: enough for a band's repertoire, bounded all the same. */
export const REMEMBERED_FILES = 50

/** The device's localStorage, or null where the browser refuses it (private mode, blocked cookies). */
export function openTabReaderStorage() {
  try {
    return window.localStorage
  } catch {
    return null
  }
}

function readJson(storage, key) {
  try {
    return JSON.parse(storage?.getItem(key) ?? 'null')
  } catch {
    return null
  }
}

function writeJson(storage, key, value) {
  try {
    storage?.setItem(key, JSON.stringify(value))
  } catch {
    // Not remembered, still applied for this visit.
  }
}

export function readPanel(storage) {
  const stored = readJson(storage, PANEL_KEY)
  return {
    visible: typeof stored?.visible === 'boolean' ? stored.visible : DEFAULT_PANEL.visible,
    tab: Object.values(PANEL_TABS).includes(stored?.tab) ? stored.tab : DEFAULT_PANEL.tab
  }
}

export function writePanel(storage, panel) {
  writeJson(storage, PANEL_KEY, { visible: !!panel.visible, tab: panel.tab })
}

/** The tracks last shown for this file, kept to those it still has, or null when nothing usable is stored. */
export function readShownTracks(storage, fileId, trackCount) {
  const entries = readJson(storage, TRACKS_KEY)
  if (!Array.isArray(entries)) return null
  const entry = entries.find((item) => Array.isArray(item) && item[0] === fileId)
  if (!Array.isArray(entry?.[1])) return null
  const tracks = [
    ...new Set(
      entry[1].filter((index) => Number.isInteger(index) && index >= 0 && index < trackCount)
    )
  ]
  return tracks.length ? tracks.sort((a, b) => a - b) : null
}

export function writeShownTracks(storage, fileId, tracks) {
  const entries = readJson(storage, TRACKS_KEY)
  const others = Array.isArray(entries)
    ? entries.filter((item) => Array.isArray(item) && item[0] !== fileId)
    : []
  writeJson(storage, TRACKS_KEY, [[fileId, [...tracks]], ...others].slice(0, REMEMBERED_FILES))
}
