/**
 * Formats a byte count using French units (o / Ko / Mo / Go).
 *
 * @param {number|null|undefined} bytes
 * @param {{ decimals?: number }} [options]
 * @returns {string}
 */
export function formatBytes(bytes, { decimals = 1 } = {}) {
  if (bytes === null || bytes === undefined) return '—'
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(decimals)} Ko`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(decimals)} Mo`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(decimals === 1 ? 2 : decimals)} Go`
}
