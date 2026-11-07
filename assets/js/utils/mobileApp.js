export const PLAY_STORE_URL = 'https://play.google.com/store/apps/details?id=com.musicall.app'
export const APP_STORE_URL = 'https://apps.apple.com/fr/app/musicall-musiciens-groupes/id6828999540'

const isAppleMobile = ({ userAgent, maxTouchPoints }) =>
  /iPhone|iPad|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && maxTouchPoints > 1)

/**
 * The stores to point this device at (#1339): its own one on a phone or a tablet, both on a computer. iPadOS reports
 * a desktop Mac user agent, which only its touch points give away.
 */
export function mobileAppStores({ userAgent = '', maxTouchPoints = 0 } = {}) {
  if (isAppleMobile({ userAgent, maxTouchPoints })) return ['ios']
  if (/Android/.test(userAgent)) return ['android']

  return ['android', 'ios']
}

/** Someone already signed in on the app has nothing to download. */
export function offersMobileApp(userProfile) {
  return !userProfile?.has_mobile_app
}
