/**
 * The image a chat message will carry, checked before it is sent (#973).
 *
 * Mirrors ChatImageConverter::ACCEPTED_MIME_TYPES and MAX_UPLOAD_SIZE, so a PDF dropped on the
 * composer or a 40 MB scan is refused with a sentence instead of being uploaded to earn a 422.
 */

export const ACCEPTED_IMAGE_TYPES = Object.freeze([
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif'
])

/** Decimal, like Symfony reads the server's `25M`. */
export const MAX_IMAGE_BYTES = 25_000_000

export const UNSUPPORTED_IMAGE_MESSAGE =
  "Format d'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)."
export const IMAGE_TOO_LARGE_MESSAGE = "L'image est trop volumineuse, la limite est de 25 Mo."

/**
 * @param {{type: string, size: number}} file
 * @returns {string|null} why the file cannot be sent, or null when it can
 */
export function imageRefusalFor(file) {
  if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
    return UNSUPPORTED_IMAGE_MESSAGE
  }

  if (file.size > MAX_IMAGE_BYTES) {
    return IMAGE_TOO_LARGE_MESSAGE
  }

  return null
}

/**
 * The first image among what was pasted or dropped. A message carries one image, so the rest are
 * ignored rather than refused: a clipboard often holds the same picture in several formats.
 *
 * @param {Iterable<{type: string}>} files
 */
export function firstImageAmong(files) {
  return Array.from(files).find((file) => file.type.startsWith('image/')) ?? null
}
