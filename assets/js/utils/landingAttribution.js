const UTM_FIELDS = ['utm_source', 'utm_medium', 'utm_campaign']
// Where a failed Google or Apple sign-in sends the visitor back from: not a site that brought them. Same list
// in src/Service/User/RegistrationCampaign.php.
const SIGN_IN_HOSTS = ['accounts.google.com', 'appleid.apple.com']

/**
 * What brought a visitor to the site (#1369): the campaign the landing URL names, and the site they came
 * from, its host only, never the page or its query. A visitor coming from MusicAll itself came from nowhere.
 * Null when there is nothing to say. Lowercased like the server stores it; the server checks and caps every
 * value, so this only leaves out what says nothing.
 */
export function landingAttribution(landingUrl, referrer, ownHost) {
  const query = new URL(landingUrl).searchParams
  const attribution = {}

  for (const field of UTM_FIELDS) {
    const value = query.get(field)?.trim().toLowerCase()
    if (value) attribution[field] = value
  }

  const referrerHost = hostOf(referrer)
  if (
    referrerHost &&
    withoutWww(referrerHost) !== withoutWww(ownHost.toLowerCase()) &&
    !SIGN_IN_HOSTS.includes(referrerHost)
  ) {
    attribution.referrer_host = referrerHost
  }

  return Object.keys(attribution).length > 0 ? attribution : null
}

function hostOf(url) {
  try {
    return new URL(url).hostname.toLowerCase()
  } catch {
    // A visit typed in or opened from a bookmark has an empty referrer.
    return null
  }
}

// The site answers as both musicall.com and www.musicall.com.
function withoutWww(host) {
  return host.replace(/^www\./, '')
}
