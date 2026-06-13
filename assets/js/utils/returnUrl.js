/**
 * Guards post-login `return_url` redirects against open-redirect abuse.
 *
 * Mirrors the backend `AbstractOAuthController::isValidReturnUrl()` policy for the
 * SPA login path: only same-origin relative paths are accepted, so a crafted
 * `?return_url=https://evil.example` cannot bounce a freshly authenticated user
 * off-site.
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
  if (url[0] !== '/') {
    return false
  }
  return url[1] !== '/' && url[1] !== '\\'
}
