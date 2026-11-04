import { i18n } from '../i18n/index.js'

// A document type is named by what people call it, not by its MIME subtype.
const DOCUMENT_LABELS = {
  'application/pdf': 'PDF',
  'application/zip': 'ZIP',
  'application/msword': 'Word',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
  'application/vnd.ms-excel': 'Excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel',
  'application/vnd.ms-powerpoint': 'PowerPoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PowerPoint',
  'text/plain': 'TXT',
  'text/csv': 'CSV'
}

// Subtypes whose name is not the extension people know.
const SUBTYPE_LABELS = {
  'audio/mpeg': 'MP3',
  'audio/mp4': 'M4A',
  'audio/x-m4a': 'M4A',
  'audio/wav': 'WAV',
  'audio/x-wav': 'WAV',
  'audio/wave': 'WAV',
  'image/svg+xml': 'SVG',
  'video/quicktime': 'MOV'
}

const MEDIA_KINDS = ['audio', 'image', 'video']

// Short enough to read as an extension once uppercased: « JPEG », « WEBP », « FLAC ».
const SHORT_SUBTYPE = /^[a-z0-9]{1,5}$/

/**
 * A file's type as a member reads it: « PDF », « Image JPEG », « Audio MP3 », rather than
 * « application/pdf ». An unknown long type keeps its MIME, which still says more than nothing.
 *
 * @param {string|null|undefined} mime
 * @returns {string|null}
 */
export function fileTypeLabel(mime) {
  if (!mime) return null

  const type = mime.toLowerCase().split(';')[0].trim()
  if (DOCUMENT_LABELS[type]) return DOCUMENT_LABELS[type]

  const [kind, rawSubtype = ''] = type.split('/')
  const subtype = rawSubtype.replace(/^x-/, '')
  const subtypeLabel =
    SUBTYPE_LABELS[type] ?? (SHORT_SUBTYPE.test(subtype) ? subtype.toUpperCase() : null)

  if (MEDIA_KINDS.includes(kind)) {
    const kindLabel = i18n.global.t(`band_space_files.filter.mime.${kind}`)
    return subtypeLabel ? `${kindLabel} ${subtypeLabel}` : kindLabel
  }

  return subtypeLabel ?? mime
}

/**
 * The PrimeIcons class drawn for a file of this type.
 *
 * @param {string|null|undefined} mime
 * @returns {string}
 */
export function fileIconClass(mime) {
  if (!mime) return 'pi pi-file'
  if (mime.startsWith('audio/')) return 'pi pi-volume-up'
  if (mime.startsWith('image/')) return 'pi pi-image'
  if (mime.startsWith('video/')) return 'pi pi-video'
  if (mime === 'application/pdf') return 'pi pi-file-pdf'
  return 'pi pi-file'
}
