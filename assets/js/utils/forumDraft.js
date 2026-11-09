import { isKeepableText } from './draftText.js'

/**
 * A new topic or a reply written by a visitor, kept over sign-in as a pending action (#1305) and reopened on the same
 * forum or topic page, never published on its own.
 *
 * The message is the editor's HTML as typed. It only ever goes back into the editor, which parses it against its
 * schema and drops whatever it does not know (scripts, event handlers, a pasted `data:` image).
 */
export const FORUM_TOPIC_CREATE = 'forum.topic_create'
export const FORUM_REPLY = 'forum.reply'

/** The payload, or null when nothing was typed (a visitor picking an image first) or it is too long to keep. */
export function topicDraftPayload({ forumSlug, title, html }) {
  const draft = readTopicDraft({ forumSlug, title, html })
  if (draft === null || (draft.title.trim() === '' && isBlankEditorHtml(draft.html))) return null

  return draft
}

/** The payload read back from localStorage, or null when it cannot be one. */
export function readTopicDraft(payload) {
  if (!payload || typeof payload !== 'object') return null
  const { forumSlug, title, html } = payload
  if (!isSlug(forumSlug) || !isKeepableText(title) || !isKeepableText(html)) return null

  return { forumSlug, title, html }
}

/** The payload, or null when nothing was typed (a visitor picking an image first) or it is too long to keep. */
export function replyDraftPayload({ topicSlug, html }) {
  const draft = readReplyDraft({ topicSlug, html })
  if (draft === null || isBlankEditorHtml(draft.html)) return null

  return draft
}

/** The payload read back from localStorage, or null when it cannot be one. */
export function readReplyDraft(payload) {
  if (!payload || typeof payload !== 'object') return null
  const { topicSlug, html } = payload
  if (!isSlug(topicSlug) || !isKeepableText(html)) return null

  return { topicSlug, html }
}

/**
 * Whether the topic can still be created where it was written.
 *
 * @param {{ forum: object | null, forumSlug: string | undefined, pageForumSlug: string }} facts  `forum` null
 *   when it is gone, `forumSlug` the draft's
 * @returns {'available' | 'forum_gone'}
 */
export function topicCreateAvailability({ forum, forumSlug, pageForumSlug }) {
  return forum && forumSlug === pageForumSlug ? 'available' : 'forum_gone'
}

/**
 * Whether the reply can still be posted to its topic.
 *
 * @param {{ topic: {slug: string, is_locked: boolean} | null, topicSlug: string | undefined }} facts  `topic` null
 *   when it is gone, `topicSlug` the draft's
 * @returns {'available' | 'topic_gone' | 'topic_locked'}
 */
export function replyAvailability({ topic, topicSlug }) {
  if (!topic || topic.slug !== topicSlug) return 'topic_gone'
  if (topic.is_locked) return 'topic_locked'

  return 'available'
}

/**
 * Whether the editor's HTML holds nothing: TipTap gives `<p></p>` for an empty editor, and empty paragraphs or line
 * breaks for one cleared by hand. An image or a video is content.
 */
export function isBlankEditorHtml(html) {
  return html.replace(/<\/?p>|<br\s*\/?>|&nbsp;|\s/g, '') === ''
}

function isSlug(value) {
  return typeof value === 'string' && value.length > 0
}
