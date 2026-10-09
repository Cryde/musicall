import { onMounted, onUnmounted, toValue, watch } from 'vue'
import { createBandSpaceLiveRefresher, onBandSpaceChange } from '../utils/bandSpaceLiveRefresh.js'

/**
 * Refetches the calling screen when another member changes one of its modules (#1157), through the
 * `band_space_changed` signal the backend publishes after each write (#1102).
 *
 * The signal carries no content, only "module X of space Y changed", so `refresh` re-reads through
 * the access-checked API. The author receives it too, which costs one refetch. `refresh` must leave
 * what the member is typing alone; `isBusy` holds the refetch while it would pull the screen from
 * under their hand, such as a card being dragged.
 *
 * @param {object} options
 * @param {import('vue').MaybeRefOrGetter<string | null>} options.bandSpaceId
 * @param {string[]} options.modules BandSpaceModule values
 * @param {() => unknown} options.refresh
 * @param {import('vue').MaybeRefOrGetter<boolean>} [options.isBusy]
 */
export function useBandSpaceLiveRefresh({ bandSpaceId, modules, refresh, isBusy = false }) {
  const refresher = createBandSpaceLiveRefresher({
    bandSpaceId: () => toValue(bandSpaceId),
    modules,
    refresh,
    isBusy: () => toValue(isBusy)
  })
  let unsubscribe = null

  watch(
    () => toValue(isBusy),
    (busy) => {
      if (!busy) refresher.resume()
    }
  )

  onMounted(() => {
    unsubscribe = onBandSpaceChange(refresher.handle)
  })

  onUnmounted(() => {
    unsubscribe?.()
    refresher.cancel()
  })
}
