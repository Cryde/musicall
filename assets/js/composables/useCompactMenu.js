import { useMediaQuery } from '@vueuse/core'
import { ref, watch } from 'vue'

/** Below Tailwind's `lg`: the same query as the `max-lg:` variant, so the two never disagree. */
export const COMPACT_NAV_MEDIA_QUERY = '(width < 64rem)'

/**
 * A header's phone menu: whether the header is compact, whether its drawer is open, and the focus
 * handed back to the menu button when the drawer closes.
 */
export function useCompactMenu(menuButton) {
  const isCompactNav = useMediaQuery(COMPACT_NAV_MEDIA_QUERY)
  const isMenuOpen = ref(false)

  // Widening past lg removes the drawer; it must not come back open when the window narrows again.
  watch(isCompactNav, (isCompact) => {
    if (!isCompact) isMenuOpen.value = false
  })

  // PrimeVue's drawer does not hand focus back. After a link, the next page may have taken it already.
  function returnFocus() {
    if (document.activeElement === document.body) {
      menuButton.value?.focus()
    }
  }

  return { isCompactNav, isMenuOpen, returnFocus }
}
