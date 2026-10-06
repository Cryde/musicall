/**
 * What a report is about, as the report dialog and the moderation screens show it (#1116).
 *
 * The reason and type values mirror ReportReason and ReportTargetType on the server.
 */

export const REPORT_REASON_OPTIONS = [
  { value: 'spam', label: 'Spam' },
  { value: 'harassment', label: 'Harcèlement' },
  { value: 'inappropriate', label: 'Contenu inapproprié' },
  { value: 'fake', label: 'Faux profil ou arnaque' },
  { value: 'other', label: 'Autre' }
]

export const REPORT_DETAILS_MAX_LENGTH = 500

const TARGET_TYPE_LABELS = {
  user: 'Profil',
  announce: 'Annonce',
  message: 'Message privé',
  band_chat_message: 'Discussion de groupe',
  profile_media: 'Média',
  forum_post: 'Message du forum',
  comment: 'Commentaire',
  publication: 'Publication'
}

const LIVE_STATES = {
  unchanged: { label: 'Inchangé', severity: 'success' },
  edited: { label: 'Modifié depuis', severity: 'warn' },
  removed: { label: 'Supprimé', severity: 'danger' }
}

const OUTCOME_LABELS = {
  dismissed: 'Classé sans suite',
  account_suspended: 'Compte suspendu'
}

export function reportReasonLabel(reason) {
  return REPORT_REASON_OPTIONS.find((option) => option.value === reason)?.label ?? reason
}

export function reportTargetTypeLabel(type) {
  return TARGET_TYPE_LABELS[type] ?? type
}

/** @returns {{label: string, severity: string}|null} null on the list, which does not compute it. */
export function reportLiveState(state) {
  return LIVE_STATES[state] ?? null
}

export function reportOutcomeLabel(outcome) {
  return OUTCOME_LABELS[outcome] ?? null
}

export function reportExcerpt(text, maxLength = 120) {
  const flattened = (text ?? '').replace(/\s+/g, ' ').trim()

  return flattened.length > maxLength ? `${flattened.slice(0, maxLength - 1)}…` : flattened
}

/** The last segment of an IRI, for a payload that carries `@id` and no `id`. */
export function idFromIri(iri) {
  return (
    String(iri ?? '')
      .split('/')
      .filter(Boolean)
      .at(-1) ?? ''
  )
}

/**
 * Whether the viewer may be offered « Signaler » on content by this author.
 *
 * The username comes from the token and is there at once; the id arrives with the profile fetch, which
 * is not awaited. An id-only author is therefore unknown until that lands, and unknown counts as no:
 * offering a report on your own content for a second is worse than showing the button a second late.
 *
 * @param {{id?: string|null, username?: string|null}} viewer
 * @param {{id?: string|null, username?: string|null}} author
 */
export function canReportContent(viewer, author) {
  if (author.username) {
    return !!viewer.username && viewer.username !== author.username
  }
  if (author.id) {
    return !!viewer.id && viewer.id !== author.id
  }

  return !!viewer.username
}

/**
 * Where « Voir le contenu » goes for a report, from the snapshot context the server stored.
 *
 * A private conversation has no link on purpose: an administrator cannot open someone's messages, so
 * the screen names what it was instead.
 *
 * @returns {{to: object|null, text: string}}
 */
export function reportContentLink(report) {
  const context = report.snapshot_context ?? {}

  switch (report.target_type) {
    case 'user':
    case 'announce':
      return context.username
        ? linkTo({ name: 'app_user_public_profile', params: { username: context.username } })
        : noLink()
    case 'profile_media':
      return context.username
        ? linkTo({
            name:
              context.profile === 'teacher'
                ? 'app_user_teacher_profile'
                : 'app_user_musician_profile',
            params: { username: context.username }
          })
        : noLink()
    case 'forum_post':
      return context.topic_slug
        ? linkTo({
            name: 'forum_topic_item',
            params: forumTopicParams(context.topic_slug, context.topic_page),
            hash: `#post-${report.target_id}`
          })
        : noLink()
    case 'comment':
    case 'publication':
      return context.publication_slug
        ? linkTo({
            name: context.is_course ? 'app_course_show' : 'app_publication_show',
            params: { slug: context.publication_slug }
          })
        : noLink()
    case 'message':
      return { to: null, text: 'Conversation privée' }
    case 'band_chat_message':
      return {
        to: null,
        text: context.band_space_name
          ? `Discussion du groupe ${context.band_space_name}`
          : 'Discussion de groupe'
      }
    default:
      return noLink()
  }
}

function forumTopicParams(slug, page) {
  const pageNumber = Number(page)

  return pageNumber > 1 ? { slug, page: String(pageNumber) } : { slug }
}

function linkTo(to) {
  return { to, text: 'Voir le contenu' }
}

function noLink() {
  return { to: null, text: 'Contenu introuvable' }
}
