<template>
  <div class="mt-8">
    <Divider />

    <CommentForm class="mb-6" />

    <div v-if="commentStore.isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else>
      <div class="text-right text-sm text-surface-500 dark:text-surface-400 mb-4">
        <template v-if="commentStore.totalComments === 0">
          Il n'y a pas encore de commentaires
        </template>
        <template v-else-if="commentStore.totalComments === 1">
          1 commentaire
        </template>
        <template v-else>
          {{ commentStore.totalComments }} commentaires
        </template>
      </div>

      <div class="flex flex-col gap-4">
        <CommentItem
          v-for="comment in commentStore.comments"
          :key="comment.id"
          :comment="comment"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import Divider from 'primevue/divider'
import { onMounted, onUnmounted, watch } from 'vue'
import { useCommentStore } from '../../store/comment/comment.js'
import CommentForm from './CommentForm.vue'
import CommentItem from './CommentItem.vue'

const props = defineProps({
  threadId: {
    type: [String, Number],
    required: true
  }
})

const commentStore = useCommentStore()

onMounted(() => {
  if (props.threadId) {
    commentStore.loadThread(props.threadId)
  }
})

watch(
  () => props.threadId,
  (newThreadId) => {
    if (newThreadId) {
      commentStore.loadThread(newThreadId)
    }
  }
)

onUnmounted(() => {
  commentStore.clear()
})
</script>
