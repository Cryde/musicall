import { onActivated, onDeactivated, onMounted, onUnmounted, shallowReactive } from 'vue'
import { useRoute } from 'vue-router'
import { useUserSecurityStore } from '../store/user/security.js'
import { keepPendingAction, openPendingActionStorage } from '../utils/pendingAction.js'

/**
 * The resumer of each kind of pending action (#1303), registered by the page that can reopen it for as long as it
 * is mounted. `PendingActionPrompt` watches it, so a page that mounts after sign-in is still offered its action.
 */
export const pendingActionResumers = shallowReactive(new Map())

/**
 * Lets the calling page resume `kind` after sign-in. The resumer receives the payload and answers
 * `{ label, resume }`, `label` naming the action in « Reprendre : ... » and `resume` reopening it prefilled, or
 * `{ unavailable }` with the sentence to show when it is no longer possible. It may be async, to check first.
 * Register it from a component mounted with the page, not from a lazily mounted modal, and keep in the payload
 * only what the action needs: it sits in localStorage, readable by the next person on a shared computer.
 *
 * @param {string} kind  for example `announce.create`
 * @param {(payload: unknown) => Promise<{ label?: string, resume?: () => void, unavailable?: string }> | object} resumer
 */
export function usePendingActionResumer(kind, resumer) {
  const register = () => pendingActionResumers.set(kind, resumer)
  const unregister = () => {
    if (pendingActionResumers.get(kind) === resumer) pendingActionResumers.delete(kind)
  }
  onMounted(register)
  onUnmounted(unregister)
  // A page kept alive (the search) is not remounted on the way back, while the page visited meanwhile may have
  // replaced and then removed its resumer of the same kind.
  onActivated(register)
  onDeactivated(unregister)
}

/**
 * Keeps what a visitor started on the current page, to resume it there after sign-in. Call it where the visitor is
 * stopped by the sign-in prompt; it does nothing for a signed in member.
 */
export function usePendingAction() {
  const route = useRoute()
  const userSecurityStore = useUserSecurityStore()

  /**
   * @param {{ kind: string, payload: unknown, stoppedOnSubmit: boolean, route?: string }} action  plain values
   *   only; `route` when the visitor comes back to another page than this one
   * @returns {boolean} whether it was kept
   */
  function keep({ kind, payload, stoppedOnSubmit, route: resumeOn = route.fullPath }) {
    return keepPendingAction(
      openPendingActionStorage(),
      { kind, route: resumeOn, payload, stoppedOnSubmit },
      {
        isVisitor: !userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated
      }
    )
  }

  return { keep }
}
