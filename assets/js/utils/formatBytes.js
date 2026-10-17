import { byteUnits, noValue } from '../i18n/formatLocale.js'

/**
 * Formats a byte count in the active locale's units (o / Ko / Mo / Go, B / KB / MB / GB).
 *
 * The figure keeps a dot in French too: QuotaExceededException writes the same figures server side
 * with a dot, and its message is read next to the quota bar.
 *
 * @param {number|null|undefined} bytes
 * @param {{ decimals?: number }} [options]
 * @returns {string}
 */
export function formatBytes(bytes, { decimals = 1 } = {}) {
  if (bytes === null || bytes === undefined) return noValue()
  const [byte, kilobyte, megabyte, gigabyte] = byteUnits()
  if (bytes < 1024) return `${bytes} ${byte}`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(decimals)} ${kilobyte}`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(decimals)} ${megabyte}`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(decimals === 1 ? 2 : decimals)} ${gigabyte}`
}
