/**
 * « Poster une découverte » and « Poster un cours » started by a visitor, kept over sign-in as a pending action
 * (#1305). Only the link and the course's category: the title and description come from the video's preview, which
 * needs an account, so the visitor is stopped before it and the form reopens on the link, previewed this time.
 */
export const PUBLICATION_DISCOVER = 'publication.discover'
export const COURSE_CREATE = 'course.create'

// Longer than any YouTube link, short enough to be no use for anything else.
const MAX_URL_LENGTH = 2048

/** The payload, or null when the link is too long to be one. */
export function discoverDraftPayload({ url }) {
  return isKeepableUrl(url) ? { url } : null
}

/** What reopens the discover form: an unusable link comes back empty, for the member to paste again. */
export function readDiscoverDraft(payload) {
  return { url: draftUrl(payload) }
}

/** The payload, or null when the link is too long to be one. */
export function courseDraftPayload({ url, categoryId }) {
  return isKeepableUrl(url) ? { url, categoryId: categoryId ?? null } : null
}

/**
 * What reopens the course form. A category gone from the list, or a link that cannot be one, is left out, so the form
 * asks for it again.
 *
 * @param {unknown} payload  read back from localStorage
 * @param {Array<{id: number}>} categories
 */
export function readCourseDraft(payload, categories) {
  const categoryId = payload && typeof payload === 'object' ? payload.categoryId : null

  return {
    url: draftUrl(payload),
    category: categories.find((category) => category.id === categoryId) ?? null
  }
}

function draftUrl(payload) {
  const url = payload && typeof payload === 'object' ? payload.url : null

  return isKeepableUrl(url) ? url : ''
}

function isKeepableUrl(url) {
  return typeof url === 'string' && url.length <= MAX_URL_LENGTH
}
