/**
 * Saves a blob to disk under a chosen name.
 *
 * A temporary anchor rather than assigning to window.location, because only the anchor's download
 * attribute lets the caller name the file. The object URL is revoked straight away so the blob does
 * not sit in memory for the life of the page.
 */
export function downloadBlob(blob, filename) {
  const objectUrl = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = objectUrl
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  document.body.removeChild(anchor)
  URL.revokeObjectURL(objectUrl)
}

/**
 * The filename a response asks to be saved as, or null when it does not say.
 *
 * Prefers the RFC 5987 filename* parameter, which is the one that survives accents: the plain
 * filename is a deliberately ASCII-only fallback (see App\Http\ContentDisposition), so reading it
 * first would turn "Répétition générale.pdf" into "Repetition-generale.pdf".
 */
export function filenameFromContentDisposition(header) {
  if (!header) {
    return null
  }

  const encoded = header.match(/filename\*=utf-8''([^;]+)/i)
  if (encoded) {
    try {
      return decodeURIComponent(encoded[1].trim())
    } catch {
      // A malformed percent-escape should fall through to the ASCII fallback below.
    }
  }

  const plain = header.match(/filename="?([^";]+)"?/i)

  return plain ? plain[1].trim() : null
}
