/**
 * Guitar Pro 3 to 8, the formats the tab reader opens. Recognised by name, never by mime type: GP3 to
 * GP5 and GPX are stored as application/octet-stream and a .gp as application/zip (#1428).
 */
export const GUITAR_PRO_EXTENSIONS = Object.freeze(['gp', 'gp3', 'gp4', 'gp5', 'gpx'])

export function isGuitarProName(name) {
  if (typeof name !== 'string') return false
  const dot = name.lastIndexOf('.')
  if (dot <= 0) return false
  return GUITAR_PRO_EXTENSIONS.includes(name.slice(dot + 1).toLowerCase())
}

/** The song's name as the reader titles it: the file name without its Guitar Pro extension. */
export function guitarProTitle(name) {
  if (!isGuitarProName(name)) return name ?? ''
  return name.slice(0, name.lastIndexOf('.'))
}
