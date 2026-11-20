import { computed, readonly, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useIsBelowMd } from './useIsPhone.js'

// One for the whole app: AppBandLayout reads it, the page asking for full screen sets it.
const focused = ref(false)

/**
 * « Plein écran » inside the Band Space, and when the Band Space header and menu are hidden.
 *
 * A page whose route has `meta.fillViewport` (the tab reader) fills the screen under the header and
 * scrolls inside itself; on a phone it is a screen of its own with its own bar, so the header goes.
 * « Plein écran » takes the header and the menu away on any screen: the page keeps its URL, so nothing
 * reloads (a song keeps playing). The page that turns it on turns it off when it goes.
 */
export function useBandLayoutFocus() {
  const route = useRoute()
  const isBelowMd = useIsBelowMd()
  const fillsViewport = computed(() => !!route.meta.fillViewport)
  const chromeHidden = computed(() => focused.value || (fillsViewport.value && isBelowMd.value))

  return {
    focused: readonly(focused),
    fillsViewport,
    chromeHidden,
    setFocused(on) {
      focused.value = on
    }
  }
}
