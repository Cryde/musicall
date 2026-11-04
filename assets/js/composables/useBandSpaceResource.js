import { onMounted, reactive, toValue } from 'vue'
import { useBandSpaceLiveRefresh } from './useBandSpaceLiveRefresh.js'

/**
 * One read of a Band Space screen, kept live: loaded on mount, read again quietly when another member
 * changes one of `modules` (#1157), and left as it is on screen when that quiet read fails. A reconnect
 * reads every screen again, which is also what recovers a read that failed.
 *
 * `enabled` false skips the request, for a read the member may not make (invitations are admin only)
 * or a module they do not see. It is checked at each read; nothing reads again when it changes, since
 * a Band Space page is remounted for another space and the member's role is known before it renders.
 *
 * `set()` puts in data the screen already holds, such as an answer the server just returned, and
 * drops any read still on its way, which would otherwise land after it with the older state.
 *
 * @param {object} options
 * @param {import('vue').MaybeRefOrGetter<string>} options.bandSpaceId
 * @param {string[]} options.modules BandSpaceModule values
 * @param {(bandSpaceId: string) => Promise<unknown>} options.load
 * @param {import('vue').MaybeRefOrGetter<boolean>} [options.enabled]
 * @returns {{ data: unknown, isLoading: boolean, failed: boolean, set: (data: unknown) => void }} reactive
 */
export function useBandSpaceResource({ bandSpaceId, modules, load, enabled = true }) {
  // Only the latest read may write: two can overlap, and the older one can answer last.
  let latestRead = 0

  const resource = reactive({
    data: null,
    isLoading: true,
    failed: false,
    set(data) {
      latestRead++
      resource.data = data
      resource.isLoading = false
    }
  })

  async function read({ quiet = false } = {}) {
    if (!toValue(enabled)) {
      resource.isLoading = false
      return
    }
    const thisRead = ++latestRead
    try {
      const data = await load(toValue(bandSpaceId))
      if (thisRead !== latestRead) return
      resource.data = data
      resource.failed = false
    } catch {
      if (thisRead === latestRead && !quiet) resource.failed = true
    } finally {
      // A newer read or set() ends the loading itself.
      if (thisRead === latestRead) resource.isLoading = false
    }
  }

  useBandSpaceLiveRefresh({ bandSpaceId, modules, refresh: () => read({ quiet: true }) })
  onMounted(() => read())

  return resource
}
