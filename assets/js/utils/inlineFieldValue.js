import { i18n } from '../i18n/index.js'
import { parseDurationInput } from './setlistDuration.js'

/**
 * What a song field typed in place stores (#1067), or why it cannot: `kind` is 'text', 'number' (a
 * whole number) or 'duration' (seconds, typed as mm:ss). Empty clears the value unless `required`.
 *
 * @returns {{value: string|number|null} | {error: string}}
 */
export function parseInlineFieldValue(text, { kind = 'text', required = false } = {}) {
  const trimmed = text.trim()
  if (trimmed === '') {
    return required
      ? { error: i18n.global.t('band_space_setlist.inline_field.required') }
      : { value: null }
  }
  if (kind === 'number') {
    // Digits only, so « 1e3 » is not quietly read as 1000.
    return /^\d+$/.test(trimmed)
      ? { value: Number(trimmed) }
      : { error: i18n.global.t('band_space_setlist.inline_field.whole_number') }
  }
  if (kind === 'duration') {
    const parsed = parseDurationInput(trimmed)
    return parsed.error ? { error: parsed.error } : { value: parsed.seconds }
  }
  return { value: trimmed }
}
