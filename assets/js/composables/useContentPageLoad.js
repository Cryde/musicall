import { until } from '@vueuse/core'
import { useRoute } from 'vue-router'

// The move is a router.replace: a page still there past this has not moved as it said.
const MOVE_TIMEOUT_MS = 5000

/**
 * The latest load of a page showing content written in one language (a forum, a topic, a publication), for its
 * pending action resumer (#1305). Signing in can land the page in the account's language, and its load then moves
 * it back under the content's (useContentLanguage), after `isLocaleSettled`: that remounts the page, and only the
 * copy that stays may reopen the action, or the form opens in the one about to be destroyed.
 */
export function useContentPageLoad() {
  const route = useRoute()
  let latest = null

  /**
   * @param {Promise<boolean>} load  answering whether it moved the page
   * @returns {Promise<boolean>} the same load, for the page to await as before
   */
  function track(load) {
    latest = { load, path: route.path }
    return load
  }

  /**
   * Waits for the latest load, and for its move when it made one, so the resumer's answer lands on a page that has
   * gone and is ignored, while the remounted copy resumes the action.
   *
   * @returns {Promise<boolean>} whether the content is there: false once it is gone (404)
   * @throws when no load was tracked yet, the move never came, or the load failed otherwise: the pending action is
   *   then kept for later
   */
  async function whenLoaded() {
    if (latest === null) throw new Error('The page has not started loading its content.')
    const { load, path } = latest
    let isMoved
    try {
      isMoved = await load
    } catch (error) {
      if (error?.response?.status === 404) return false
      throw error
    }
    if (isMoved) {
      await until(() => route.path).not.toBe(path, { timeout: MOVE_TIMEOUT_MS })
      if (route.path === path) throw new Error('The page did not move to its content language.')
    }

    return true
  }

  return { track, whenLoaded }
}
