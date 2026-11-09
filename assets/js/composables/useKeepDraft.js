import { usePendingAction } from './usePendingAction.js'

/**
 * Keeps what a visitor wrote when submitting stopped them for sign-in (#1305), so the form reopens on its own after.
 *
 * @returns {(kind: string, payload: object | null) => boolean} whether it was kept. A null payload, what a draft
 *   builder answers when there is nothing worth keeping, is not.
 */
export function useKeepDraft() {
  const pendingAction = usePendingAction()

  return (kind, payload) =>
    payload !== null && pendingAction.keep({ kind, payload, stoppedOnSubmit: true })
}
