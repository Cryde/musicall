/**
 * What a report is about, as the report dialog and the moderation screens show it (#1116).
 *
 * The reason and type values mirror ReportReason and ReportTargetType on the server.
 */

import { i18n } from '../i18n/index.js'

// Labels are getters so they are translated when shown, in whatever language is active then.
function reasonOption(value, labelKey) {
  return {
    value,
    get label() {
      return i18n.global.t(labelKey)
    }
  }
}

export const REPORT_REASON_OPTIONS = [
  reasonOption('spam', 'shared.report.reason.spam'),
  reasonOption('harassment', 'shared.report.reason.harassment'),
  reasonOption('inappropriate', 'shared.report.reason.inappropriate'),
  reasonOption('fake', 'shared.report.reason.fake'),
  reasonOption('other', 'shared.report.reason.other')
]

export const REPORT_DETAILS_MAX_LENGTH = 500

const TARGET_TYPE_LABEL_KEYS = {
  user: 'shared.report.target_type.user',
  announce: 'shared.report.target_type.announce',
  message: 'shared.report.target_type.message',
  band_chat_message: 'shared.report.target_type.band_chat_message',
  profile_media: 'shared.report.target_type.profile_media',
  forum_post: 'shared.report.target_type.forum_post',
  comment: 'shared.report.target_type.comment',
  publication: 'shared.report.target_type.publication'
}

const LIVE_STATES = {
  unchanged: { labelKey: 'shared.report.live_state.unchanged', severity: 'success' },
  edited: { labelKey: 'shared.report.live_state.edited', severity: 'warn' },
  removed: { labelKey: 'shared.report.live_state.removed', severity: 'danger' }
}

const OUTCOME_LABEL_KEYS = {
  dismissed: 'shared.report.outcome.dismissed',
  account_suspended: 'shared.report.outcome.account_suspended'
}

export function reportReasonLabel(reason) {
  return REPORT_REASON_OPTIONS.find((option) => option.value === reason)?.label ?? reason
}

export function reportTargetTypeLabel(type) {
  const labelKey = TARGET_TYPE_LABEL_KEYS[type]
  return labelKey ? i18n.global.t(labelKey) : type
}

/** @returns {{label: string, severity: string}|null} null on the list, which does not compute it. */
export function reportLiveState(state) {
  const liveState = LIVE_STATES[state]
  return liveState
    ? { label: i18n.global.t(liveState.labelKey), severity: liveState.severity }
    : null
}

export function reportOutcomeLabel(outcome) {
  const labelKey = OUTCOME_LABEL_KEYS[outcome]
  return labelKey ? i18n.global.t(labelKey) : null
}

/**
 * What a reporter is told their report was about (#1125), from the type and the label the server kept
 * of it. A private conversation never has a label.
 */
export function reportSubject(type, label) {
  const { t } = i18n.global
  switch (type) {
    case 'user':
      return label
        ? t('shared.report.subject.user', { label })
        : t('shared.report.subject.any_user')
    case 'announce':
      return label
        ? t('shared.report.subject.announce', { label })
        : t('shared.report.subject.any_announce')
    case 'profile_media':
      return label
        ? t('shared.report.subject.profile_media', { label })
        : t('shared.report.subject.any_profile_media')
    case 'forum_post':
      return label
        ? t('shared.report.subject.forum_post', { label })
        : t('shared.report.subject.any_forum_post')
    case 'comment':
      return label
        ? t('shared.report.subject.comment', { label })
        : t('shared.report.subject.any_comment')
    case 'publication':
      return label
        ? t('shared.report.subject.publication', { label })
        : t('shared.report.subject.any_publication')
    case 'band_chat_message':
      return label
        ? t('shared.report.subject.band_chat_message', { label })
        : t('shared.report.subject.any_band_chat_message')
    case 'message':
      return t('shared.report.subject.message')
    default:
      return t('shared.report.subject.other')
  }
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
      return { to: null, text: i18n.global.t('shared.report.link.private_conversation') }
    case 'band_chat_message':
      return {
        to: null,
        text: context.band_space_name
          ? i18n.global.t('shared.report.link.band_chat', { band_space: context.band_space_name })
          : i18n.global.t('shared.report.link.any_band_chat')
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
  return { to, text: i18n.global.t('shared.report.link.view_content') }
}

function noLink() {
  return { to: null, text: i18n.global.t('shared.report.link.not_found') }
}
