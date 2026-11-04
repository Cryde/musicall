import { computed } from 'vue'
import { useIsBelowMd } from './useIsPhone.js'

// Docked to the bottom edge, full width, rounded on top only, clear of the home indicator: a dialog
// read as the app's sheets are.
const SHEET_PROPS = {
  position: 'bottom',
  draggable: false,
  style: { width: '100%', maxHeight: '85dvh' },
  pt: {
    root: {
      class: '!m-0 !max-w-full !rounded-b-none !rounded-t-2xl pb-[env(safe-area-inset-bottom)]'
    }
  }
}

/**
 * A PrimeVue Dialog's size and place: its own width from `md` up, a bottom sheet below. A fixed width
 * of 28rem or more is wider than a phone, and PrimeVue gives a dialog no maximum.
 *
 * Bind it whole: `<Dialog v-bind="sheetDialog">`.
 *
 * @param {string} width  the desktop width, e.g. '36rem'
 */
export function useSheetDialog(width) {
  const isBelowMd = useIsBelowMd()

  return computed(() => (isBelowMd.value ? SHEET_PROPS : { style: { width } }))
}
