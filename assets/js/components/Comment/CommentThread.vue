<template>
  <div class="mt-8">
    <Divider />

    <CommentForm class="mb-6" />

    <div v-if="commentStore.isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else>
      <div class="text-right text-sm text-surface-500 dark:text-surface-400 mb-4">
        <template v-if="rootComments.length === 0">
          Il n'y a pas encore de commentaires
        </template>
        <template v-else-if="rootComments.length === 1">
          1 commentaire
        </template>
        <template v-else>
          {{ rootComments.length }} commentaires
        </template>
      </div>

      <div class="flex flex-col gap-4">
        <CommentItem
          v-for="comment in rootComments"
          :key="comment.id"
          :comment="comment"
          :replies="repliesByParentId[comment.id] || []"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import Divider from 'primevue/divider'
import { computed, onMounted, onUnmounted, watch } from 'vue'
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

const rootComments = computed(() =>
  commentStore.comments.filter((c) => c.parent_id === null || c.parent_id === undefined)
)

// Backend returns replies ordered by creation_datetime ASC (see Comment::$replies
// OrderBy). The collection endpoint preserves that order, so no client-side sort needed.
const repliesByParentId = computed(() => {
  const map = {}
  for (const c of commentStore.comments) {
    if (c.parent_id !== null && c.parent_id !== undefined) {
      if (!map[c.parent_id]) {
        map[c.parent_id] = []
      }
      map[c.parent_id].push(c)
    }
  }
  return map
})

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
