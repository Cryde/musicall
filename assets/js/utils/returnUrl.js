/**
 * Guards post-login `return_url` redirects against open-redirect abuse.
 *
 * The matched pair of the backend `App\Http\ReturnUrl::isSafe()`, pinned on both
 * sides by tests carrying the same cases. Only same-origin relative paths are
 * accepted here, so a crafted `?return_url=https://evil.example` cannot bounce a
 * freshly authenticated user off-site. The backend applies the same relative rule
 * and additionally accepts an absolute URL on the frontend's own host, which the
 * OAuth entry point needs and the SPA does not.
 *
 * A safe value must start with a single "/" that is NOT followed by another "/"
 * or a "\". The backslash case matters: browsers normalise "\" to "/", so
 * "/\evil.com" would otherwise resolve to the protocol-relative "//evil.com".
 *
 * @param {unknown} url
 * @returns {boolean}
 */
export function isSafeReturnUrl(url) {
  if (typeof url !== 'string' || url.length === 0) {
    return false
  }
  // The URL parser deletes every ASCII tab, CR and LF from the whole input before parsing it, so
  // "/\t/evil.example" reaches it as the protocol relative "//evil.example". Refused rather than
  // stripped and re-checked, so what is checked is what gets assigned to location.href.
  if (/[\t\r\n]/.test(url)) {
    return false
  }
  if (url[0] !== '/') {
    return false
  }
  return url[1] !== '/' && url[1] !== '\\'
}
