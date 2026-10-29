export const PLAY_STORE_URL = 'https://play.google.com/store/apps/details?id=com.musicall.app'

/**
 * There is no iOS build, so an iPhone or iPad is never pointed at the Play Store. iPadOS reports a
 * desktop Mac user agent, which only its touch points give away.
 */
export function advertisesMobileApp({ userAgent = '', maxTouchPoints = 0 } = {}) {
  if (/iPhone|iPad|iPod/.test(userAgent)) return false

  return !(/Macintosh/.test(userAgent) && maxTouchPoints > 1)
}

/** Someone already signed in on the app has nothing to download. */
export function offersMobileApp(userProfile, browser) {
  return !userProfile?.has_mobile_app && advertisesMobileApp(browser)
}
