import { useMediaQuery } from '@vueuse/core'

/** Below Tailwind's `sm` breakpoint: the same query as the `max-sm:` variant, so the two never disagree. */
export const PHONE_MEDIA_QUERY = '(width < 40rem)'

/** For what a class cannot switch on a phone: a PrimeVue prop, or rendering one list rather than another. */
export function useIsPhone() {
  return useMediaQuery(PHONE_MEDIA_QUERY)
}
