import { useI18n } from 'vue-i18n'
import { useCommentStore } from '../store/comment/comment.js'
import {
  COMMENT_POST,
  commentAvailability,
  commentDraftPayload,
  readCommentDraft
} from '../utils/commentDraft.js'
import { useKeepDraft } from './useKeepDraft.js'
import { usePendingActionResumer } from './usePendingAction.js'

/**
 * Keeps the comment a visitor wrote when « Poster le commentaire » stops them (#1305).
 *
 * @returns {(draft: { threadId: number, content: string }) => boolean} whether it was kept
 */
export function useKeepCommentDraft() {
  const keepDraft = useKeepDraft()

  return (draft) => keepDraft(COMMENT_POST, commentDraftPayload(draft))
}

/**
 * Puts the kept comment back in the form of the calling publication or course page after sign-in, once the
 * publication is checked to be still there. Registered by the page, as the comment block mounts only once the
 * publication has loaded.
 *
 * @param {{ publication: import('vue').Ref<object|null>, whenLoaded: () => Promise<boolean> }} page  `whenLoaded`
 *   from useContentPageLoad
 */
export function useCommentDraftResumer({ publication, whenLoaded }) {
  const { t } = useI18n()
  const commentStore = useCommentStore()

  usePendingActionResumer(COMMENT_POST, async (payload) => {
    const draft = readCommentDraft(payload)
    const isPublicationThere = await whenLoaded()
    const availability = commentAvailability({
      publication: isPublicationThere ? publication.value : null,
      threadId: draft?.threadId
    })
    if (availability !== 'available') {
      return { unavailable: t('publication.pending.comment_publication_gone') }
    }

    return {
      label: t('publication.pending.comment_label'),
      resume: () => commentStore.restoreDraft(draft.content)
    }
  })
}
