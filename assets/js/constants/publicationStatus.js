import { i18n } from '../i18n/index.js'

// Mirrors `Publication::STATUS_*`, which courses share.
const PUBLICATION_STATUS_LABEL_KEYS = {
  0: 'publication.user_list.status.draft',
  1: 'publication.user_list.status.online',
  2: 'publication.user_list.status.pending'
}

// Mirrors `Gallery::STATUS_*`: the numbers are not a publication's, and an online gallery is « En ligne ».
const GALLERY_STATUS_LABEL_KEYS = {
  0: 'publication.gallery.status.online',
  1: 'publication.gallery.status.draft',
  2: 'publication.gallery.status.pending'
}

export function publicationStatusLabel(statusId) {
  return i18n.global.t(
    PUBLICATION_STATUS_LABEL_KEYS[statusId] ?? 'publication.user_list.status.unknown'
  )
}

export function galleryStatusLabel(status) {
  return i18n.global.t(GALLERY_STATUS_LABEL_KEYS[status] ?? 'publication.gallery.status.unknown')
}
