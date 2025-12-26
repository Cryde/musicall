import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import commentApi from '../../api/comment/comment.js'

export const useCommentStore = defineStore('comment', () => {
  const threadId = ref(null)
  const comments = ref([])
  const totalComments = ref(0)
  const isLoading = ref(false)
  const isPosting = ref(false)

  async function loadThread(id) {
    threadId.value = id
    isLoading.value = true

    try {
      const response = await commentApi.getComments(id)
      comments.value = response['hydra:member'] || response['member'] || []
      totalComments.value = response['hydra:totalItems'] || response['totalItems'] || 0
    } finally {
      isLoading.value = false
    }
  }

  async function postComment(content) {
    if (!threadId.value) return

    isPosting.value = true
    try {
      const comment = await commentApi.postComment({
        threadId: threadId.value,
        content
      })
      // Add new comment at the beginning
      comments.value = [comment, ...comments.value]
      totalComments.value += 1
      return comment
    } finally {
      isPosting.value = false
    }
  }

  function clear() {
    threadId.value = null
    comments.value = []
    totalComments.value = 0
  }

  return {
    threadId: readonly(threadId),
    comments: readonly(comments),
    totalComments: readonly(totalComments),
    isLoading: readonly(isLoading),
    isPosting: readonly(isPosting),
    loadThread,
    postComment,
    clear
  }
})
