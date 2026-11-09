import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import commentApi from '../../api/comment/comment.js'

export const useCommentStore = defineStore('comment', () => {
  const threadId = ref(null)
  const comments = ref([])
  const totalComments = ref(0)
  const isLoading = ref(false)
  const isPosting = ref(false)
  // A comment written before signing in (#1305), waiting for the comment form to take it.
  const restoredDraft = ref(null)

  async function loadThread(id) {
    threadId.value = id
    // A draft is for the thread it was written under. The form takes it while mounting, before this runs.
    restoredDraft.value = null
    isLoading.value = true

    try {
      const response = await commentApi.getComments(id)
      comments.value = response['hydra:member'] || response.member || []
      totalComments.value = response['hydra:totalItems'] || response.totalItems || 0
    } finally {
      isLoading.value = false
    }
  }

  async function postComment({ content, parentId = null }) {
    if (!threadId.value) return

    isPosting.value = true
    try {
      const comment = await commentApi.postComment({
        threadId: threadId.value,
        content,
        parentId
      })
      // Roots show newest-first; replies follow backend ASC ordering (oldest-first),
      // so prepend a root but append a reply to keep the on-screen order stable
      // until the next thread reload.
      if (parentId === null) {
        comments.value = [comment, ...comments.value]
      } else {
        comments.value = [...comments.value, comment]
      }
      totalComments.value += 1
      return comment
    } finally {
      isPosting.value = false
    }
  }

  function restoreDraft(content) {
    restoredDraft.value = content
  }

  /** The restored comment, handed over once. */
  function takeRestoredDraft() {
    const content = restoredDraft.value
    restoredDraft.value = null
    return content
  }

  function clear() {
    threadId.value = null
    comments.value = []
    totalComments.value = 0
    restoredDraft.value = null
  }

  return {
    threadId: readonly(threadId),
    comments: readonly(comments),
    totalComments: readonly(totalComments),
    isLoading: readonly(isLoading),
    isPosting: readonly(isPosting),
    restoredDraft: readonly(restoredDraft),
    loadThread,
    postComment,
    restoreDraft,
    takeRestoredDraft,
    clear
  }
})
