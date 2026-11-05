/**
 * Runs a bottom sheet's action once the sheet has gone, not as it starts closing: a dialog or a confirm
 * opened while the sheet leaves loses the page's scroll lock, which the sheet releases on its way out.
 *
 * Bind `onAfterHide` to the sheet's `@after-hide`, and call `run(action)` from its rows.
 *
 * @param {import('vue').Ref<boolean>} isOpen  the sheet's visibility
 */
export function useActionAfterSheetHide(isOpen) {
  let pending = null

  function run(action) {
    pending = action
    isOpen.value = false
  }

  function onAfterHide() {
    const action = pending
    pending = null
    action?.()
  }

  return { run, onAfterHide }
}
