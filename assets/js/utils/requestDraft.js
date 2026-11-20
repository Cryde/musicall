/**
 * The answers of a request written before signing in (« Demander un cours d'essai » #1311, « Demander un devis »
 * #1408), kept in this tab only until the visitor is signed in. Each request names its own key.
 */

const DRAFT_PREFIX = 'request-draft:'
// Long enough to sign in or sign up and come back, short enough not to greet the next person in a shared tab.
export const DRAFT_LIFETIME_MS = 2 * 60 * 60 * 1000

/** The answers a visitor gave before signing in, kept in this tab only. */
export function saveRequestDraft(storage, key, draft, now = Date.now()) {
  if (!storage || !key) return
  try {
    storage.setItem(DRAFT_PREFIX + key.toLowerCase(), JSON.stringify({ ...draft, savedAt: now }))
  } catch {
    // Storage full or blocked: the visitor answers again.
  }
}

/** Taken once, and only while it is fresh. */
export function takeRequestDraft(storage, key, now = Date.now()) {
  if (!storage || !key) return null
  const storageKey = DRAFT_PREFIX + key.toLowerCase()
  try {
    const raw = storage.getItem(storageKey)
    storage.removeItem(storageKey)
    if (!raw) return null
    const draft = JSON.parse(raw)
    if (typeof draft?.savedAt !== 'number' || now - draft.savedAt > DRAFT_LIFETIME_MS) return null
    return {
      answers: draft.answers ?? {},
      message: typeof draft.message === 'string' ? draft.message : null
    }
  } catch {
    return null
  }
}

/** Whether a draft waits under this key, without taking it. */
export function hasRequestDraft(storage, key, now = Date.now()) {
  if (!storage || !key) return false
  try {
    const raw = storage.getItem(DRAFT_PREFIX + key.toLowerCase())
    const draft = raw ? JSON.parse(raw) : null
    return typeof draft?.savedAt === 'number' && now - draft.savedAt <= DRAFT_LIFETIME_MS
  } catch {
    return false
  }
}

/** Every request draft in this tab, at logout: the next person to sign in here must not find them. */
export function clearRequestDrafts(storage) {
  if (!storage) return
  try {
    const draftKeys = []
    for (let index = 0; index < storage.length; index++) {
      const storageKey = storage.key(index)
      if (storageKey?.startsWith(DRAFT_PREFIX)) draftKeys.push(storageKey)
    }
    for (const storageKey of draftKeys) storage.removeItem(storageKey)
  } catch {
    // Blocked storage holds no draft.
  }
}

/** Reading the property itself throws when the browser blocks storage. */
export function openRequestDraftStorage() {
  try {
    return globalThis.sessionStorage ?? null
  } catch {
    return null
  }
}
