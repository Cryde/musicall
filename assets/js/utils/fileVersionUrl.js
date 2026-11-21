/**
 * A file's URL pinned to its current version, so a preview, a sound or a score is fetched again once a
 * new version is uploaded or an older one restored, rather than served from the cache.
 *
 * @param {string} url
 * @param {?string} versionId
 * @returns {string}
 */
export function withVersion(url, versionId) {
  if (!versionId) return url
  return `${url}${url.includes('?') ? '&' : '?'}v=${versionId}`
}
