/**
 * An action a visitor started before being asked to sign in, kept so its page can offer it back once they are
 * signed in (#1303). localStorage because login reloads the page and the confirmation email opens a new tab.
 *
 * Stored as `{ kind, route, payload, stoppedOnSubmit, createdAt }`, one at a time: a new one replaces the old.
 */
import { isSafeReturnUrl } from './returnUrl.js'

const STORAGE_KEY = 'pending-action'
// Long enough to sign up and confirm the email, short enough on a shared computer.
export const PENDING_ACTION_LIFETIME_MS = 2 * 60 * 60 * 1000

/** Reading the property itself throws when the browser blocks storage, hence the try. */
export function openPendingActionStorage() {
  try {
    return globalThis.localStorage ?? null
  } catch {
    return null
  }
}

/**
 * Keeps what the visitor started. Refused for a signed in member, and logout clears it (see the store). The limit,
 * as in trialRequest.js: a visitor who walks away without signing in leaves it to whoever signs in on this browser
 * within the lifetime, since both were signed out and nothing tells them apart.
 *
 * @param {{ kind: string, route: string, payload: unknown, stoppedOnSubmit: boolean }} action  `stoppedOnSubmit`:
 *   the visitor was stopped on « Publier », « Envoyer » or « Créer », so the action reopens on its own.
 * @returns {boolean} whether it was kept
 */
export function keepPendingAction(storage, action, { isVisitor, now = Date.now() }) {
  if (!storage || !isVisitor) return false
  const { kind, route, payload, stoppedOnSubmit } = action
  if (!isValidAction({ kind, route, payload, stoppedOnSubmit })) return false

  try {
    storage.setItem(
      STORAGE_KEY,
      JSON.stringify({ kind, route, payload, stoppedOnSubmit, createdAt: now })
    )
    return true
  } catch {
    // Storage full or blocked: the visitor starts again after signing in.
    return false
  }
}

/** The action still waiting, or null. One that has expired or cannot be trusted is dropped on the way. */
export function readPendingAction(storage, now = Date.now()) {
  if (!storage) return null

  try {
    const raw = storage.getItem(STORAGE_KEY)
    if (raw === null) return null
    const action = JSON.parse(raw)
    const age = now - action?.createdAt
    if (isValidAction(action ?? {}) && age >= 0 && age <= PENDING_ACTION_LIFETIME_MS) {
      return action
    }
    storage.removeItem(STORAGE_KEY)
  } catch {
    clearPendingAction(storage)
  }
  return null
}

export function clearPendingAction(storage) {
  if (!storage) return
  try {
    storage.removeItem(STORAGE_KEY)
  } catch {
    // Blocked storage holds no action.
  }
}

/** On the page of its route: the path alone, since a page may rewrite its own query string on arrival. */
export function isOnPendingActionPage(action, path) {
  return action.route.split(/[?#]/)[0] === path
}

/**
 * Hands the action to its module's resumer, which answers `{ label, resume }`, or `{ unavailable }` with the
 * sentence to show when the action is no longer possible (already a member, recipient deleted, topic locked).
 * Nothing is sent from here: `resume` only reopens the action, prefilled, for the member to confirm.
 *
 * @param {object} handlers  `drop` forgets the action, `offer(label, resume)` shows « Reprendre », `inform(sentence)`
 *   says why it is gone, `isStillOnPage` tells whether the page that registered the resumer is still there once
 *   the resumer has answered.
 * @returns {Promise<'reopened' | 'offered' | 'unavailable' | 'left' | 'invalid'>}
 */
export async function resumePendingAction(action, resumer, handlers) {
  const { drop, offer, inform, isStillOnPage } = handlers
  const outcome = await resumer(action.payload)
  if (!isStillOnPage()) return 'left'

  // A resumer that answers nothing usable is a bug in the module: kept, so the fix gets to offer it.
  if (typeof outcome !== 'object' || outcome === null) {
    console.warn(`The resumer of ${action.kind} answered no outcome.`)
    return 'invalid'
  }

  if (outcome.unavailable) {
    drop()
    inform(outcome.unavailable)
    return 'unavailable'
  }

  // Once only: a second click while the toast fades out must not open the form twice.
  let isResumed = false
  const resume = () => {
    if (isResumed) return
    isResumed = true
    drop()
    outcome.resume()
  }
  if (action.stoppedOnSubmit) {
    resume()
    return 'reopened'
  }
  offer(outcome.label, resume)
  return 'offered'
}

function isValidAction({ kind, route, payload, stoppedOnSubmit }) {
  return (
    typeof kind === 'string' &&
    kind.length > 0 &&
    isSafeReturnUrl(route) &&
    typeof stoppedOnSubmit === 'boolean' &&
    isPlainValue(payload)
  )
}

/** JSON values only, so what is read back is exactly what was written: no Date, no File, no class instance. */
function isPlainValue(value) {
  if (value === null || typeof value === 'string' || typeof value === 'boolean') return true
  if (typeof value === 'number') return Number.isFinite(value)
  if (Array.isArray(value)) return value.every(isPlainValue)
  if (typeof value === 'object' && Object.getPrototypeOf(value) === Object.prototype) {
    return Object.values(value).every(isPlainValue)
  }
  return false
}
