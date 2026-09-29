/**
 * The band name a visitor typed on /band-space, carried to the create modal. localStorage rather
 * than sessionStorage because a visitor signs up in between, and the confirmation link opens a new
 * tab. Taken once: the modal reads it and clears it, so it never resurfaces on a later creation.
 */

const STORAGE_KEY = 'band-space-draft-name'

/** Reading the property itself throws when the browser blocks storage, hence the try. */
export function openDraftNameStorage() {
  try {
    return globalThis.localStorage ?? null
  } catch {
    return null
  }
}

/** A blank name clears the draft, so an earlier attempt cannot resurface. */
export function saveDraftName(storage, name) {
  if (!storage) return

  const trimmed = name.trim()
  try {
    if (trimmed) {
      storage.setItem(STORAGE_KEY, trimmed)
    } else {
      storage.removeItem(STORAGE_KEY)
    }
  } catch {
    // Storage full or blocked: the visitor simply types the name again.
  }
}

export function takeDraftName(storage) {
  if (!storage) return ''

  try {
    const name = storage.getItem(STORAGE_KEY) ?? ''
    storage.removeItem(STORAGE_KEY)
    return name
  } catch {
    return ''
  }
}
