import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import feedbackApi from '../api/feedback/feedback.js'

/**
 * The drawer is mounted once in App.vue, so opening it is a store flag rather than a prop threaded
 * through every layout. Same shape as the Band Space create modal.
 */
export const useFeedbackStore = defineStore('feedback', () => {
  const isDrawerOpen = ref(false)
  const isSending = ref(false)
  // What a page asks the form to start with, `{ type, message }`, read by the drawer as it opens.
  const prefill = ref(null)

  function openDrawer(startWith = null) {
    prefill.value = startWith
    isDrawerOpen.value = true
  }

  function closeDrawer() {
    isDrawerOpen.value = false
  }

  async function send(payload) {
    isSending.value = true
    try {
      return await feedbackApi.send(payload)
    } finally {
      isSending.value = false
    }
  }

  return {
    isDrawerOpen,
    isSending: readonly(isSending),
    prefill: readonly(prefill),
    openDrawer,
    closeDrawer,
    send
  }
})
