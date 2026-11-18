/**
 * The pure parts of a service's « Réalisations » (#1407): what the form sends, and the lines a work shows.
 */

/** Mirrors ServiceWork::MAX_PER_SERVICE and ServiceWorkImage::MAX_PER_WORK. */
export const MAX_WORKS = 30
export const MAX_WORK_IMAGES = 12

/** Mirrors ServiceWorkImage::ACCEPTED_MIME_TYPES: what the server re-encodes, the browser only filters the picker. */
export const WORK_IMAGE_ACCEPT = '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp'

/** What the form starts from, for a new work or one being edited. */
export function workForm(work = null) {
  return {
    title: work?.title ?? '',
    credit: work?.credit ?? '',
    year: work?.year ?? null,
    link_url: work?.link_url ?? '',
    media_url: work?.media?.url ?? ''
  }
}

/** Every field, so a PATCH also empties what was cleared: a blank is sent as null. */
export function workPayload(form) {
  const text = (value) => {
    const trimmed = (value ?? '').trim()
    return trimmed === '' ? null : trimmed
  }
  return {
    title: (form.title ?? '').trim(),
    credit: text(form.credit),
    year: Number.isInteger(form.year) ? form.year : null,
    link_url: text(form.link_url),
    media_url: text(form.media_url)
  }
}

/** « Mona Lisa Overdrive, EP · 2024 », or whichever half there is. */
export function workByline(work) {
  return [work.credit, work.year].filter((part) => part !== null && part !== '').join(' · ')
}

/** The site a link leads to, as a visitor reads it: `bandcamp.com` rather than the whole address. */
export function workLinkDomain(url) {
  try {
    const { protocol, hostname } = new URL(url)
    if (protocol !== 'https:' && protocol !== 'http:') return null
    return hostname.replace(/^www\./, '')
  } catch {
    return null
  }
}

/** The items reordered as `ids` says, for the screen to follow the order it just sent. */
export function orderedBy(items, ids) {
  const byId = new Map(items.map((item) => [item.id, item]))
  return ids.map((id) => byId.get(id)).filter(Boolean)
}
