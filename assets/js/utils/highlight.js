/**
 * Wrap case-insensitive matches of each whitespace-separated token from `term`
 * around `text` with <mark>. Escapes the rest so callers can drop the result
 * straight into v-html without an XSS surface.
 *
 * Empty term or empty text returns an HTML-escaped version of the input.
 */
const ESCAPE_MAP = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;'
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (ch) => ESCAPE_MAP[ch])
}

function escapeRegex(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

export function highlightTerm(text, term) {
  const safeText = escapeHtml(text ?? '')
  if (!term || !safeText) {
    return safeText
  }

  const tokens = String(term)
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map(escapeRegex)

  if (tokens.length === 0) {
    return safeText
  }

  const pattern = new RegExp(`(${tokens.join('|')})`, 'gi')
  return safeText.replace(pattern, '<mark>$1</mark>')
}
