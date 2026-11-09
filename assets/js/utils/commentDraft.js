import { isKeepableText } from './draftText.js'

/**
 * A comment a visitor wrote under a publication or a course, kept over sign-in as a pending action (#1305) and put
 * back in the comment form of the same page, never posted on its own.
 */
export const COMMENT_POST = 'comment.post'

/** The payload, or null when nothing was typed or it is too long to keep. */
export function commentDraftPayload({ threadId, content }) {
  const draft = readCommentDraft({ threadId, content })
  if (draft === null || draft.content.trim() === '') return null

  return draft
}

/** The payload read back from localStorage, or null when it cannot be one. */
export function readCommentDraft(payload) {
  if (!payload || typeof payload !== 'object') return null
  const { threadId, content } = payload
  if (!Number.isInteger(threadId) || threadId <= 0 || !isKeepableText(content)) return null

  return { threadId, content }
}

/**
 * Whether the comment can still go under what it was written for.
 *
 * @param {{ publication: {thread?: {id: number}} | null, threadId: number | undefined }} facts  `publication` null
 *   when it is gone, `threadId` the draft's
 * @returns {'available' | 'publication_gone'}
 */
export function commentAvailability({ publication, threadId }) {
  return publication?.thread?.id !== undefined && publication.thread.id === threadId
    ? 'available'
    : 'publication_gone'
}
