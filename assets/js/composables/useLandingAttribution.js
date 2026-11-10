import { landingAttribution } from '../utils/landingAttribution.js'

// In this page's memory only, never in a cookie or browser storage, as the privacy policy promises: a
// sign-up later in the same visit sends it, a visitor who comes back another day comes without it (#1369).
let rememberedAttribution = null

/** Called once at boot, before the router's first navigation can rewrite the landing URL. */
export function rememberLandingAttribution() {
  rememberedAttribution = landingAttribution(
    window.location.href,
    document.referrer,
    window.location.hostname
  )
}

/** The fields a web sign-up sends along: utm_source, utm_medium, utm_campaign, referrer_host, those known. */
export function useLandingAttribution() {
  return { ...rememberedAttribution }
}
