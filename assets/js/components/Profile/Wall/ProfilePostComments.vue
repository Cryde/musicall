<template>
  <section class="flex flex-col gap-3 pt-3 border-t border-surface-200 dark:border-surface-700" :aria-label="t('profile.wall.comments_title')">
    <div v-if="isLoading" class="flex justify-center py-2">
      <ProgressSpinner style="width: 1.5rem; height: 1.5rem" />
    </div>
    <p v-else-if="hasLoadError" class="text-sm text-surface-500 dark:text-surface-400 m-0" role="alert">
      {{ t('profile.wall.comments_error') }}
    </p>
    <ol v-else-if="tree.length" class="list-none m-0 p-0 flex flex-col gap-3">
      <li v-for="comment in tree" :key="comment.id" class="flex flex-col gap-2">
        <ProfilePostCommentItem
          :comment="comment"
          :can-reply="userSecurityStore.isAuthenticated"
          @reply="replyingTo = comment.id"
          @delete="remove"
        />
        <ol v-if="comment.replies.length" class="list-none m-0 p-0 pl-10 flex flex-col gap-2">
          <li v-for="reply in comment.replies" :key="reply.id">
            <ProfilePostCommentItem :comment="reply" @delete="remove" />
          </li>
        </ol>
        <ProfilePostCommentForm
          v-if="replyingTo === comment.id"
          :post-id="postId"
          :parent-id="comment.id"
          autofocus
          class="pl-10"
          @posted="add"
          @cancel="replyingTo = null"
        />
      </li>
    </ol>

    <template v-if="!isLoading">
      <ProfilePostCommentForm v-if="userSecurityStore.isAuthenticated" :post-id="postId" @posted="add" />
      <p v-else class="text-sm text-surface-500 dark:text-surface-400 m-0">
      <RouterLink :to="signInRouteFrom('app_login', route)" class="text-primary font-medium">{{ t('profile.wall.login') }}</RouterLink>
      {{ t('profile.wall.login_to_comment') }}
      </p>
    </template>
  </section>
</template>

<script setup>
import ProgressSpinner from 'primevue/progressspinner'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import profileWallApi from '../../../api/user/profileWall.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { commentTree, treeWithComment, treeWithoutComment } from '../../../utils/profileWall.js'
import { signInRouteFrom } from '../../../utils/unauthenticatedRedirect.js'
import ProfilePostCommentForm from './ProfilePostCommentForm.vue'
import ProfilePostCommentItem from './ProfilePostCommentItem.vue'

const props = defineProps({
  postId: { type: String, required: true }
})

/** How far the post's comment count moved: a comment added, or a comment and its replies removed. */
const emit = defineEmits(['count-changed'])
const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const userSecurityStore = useUserSecurityStore()

const tree = ref([])
const isLoading = ref(true)
const hasLoadError = ref(false)
const replyingTo = ref(null)

onMounted(async () => {
  try {
    tree.value = commentTree(await profileWallApi.getComments(props.postId))
  } catch {
    hasLoadError.value = true
  } finally {
    isLoading.value = false
  }
})

function add(comment) {
  tree.value = treeWithComment(tree.value, comment)
  // A new comment at the bottom leaves an open reply form, and its draft, alone.
  if (comment.parent_id) {
    replyingTo.value = null
  }
  emit('count-changed', 1)
}

async function remove(comment) {
  try {
    await profileWallApi.deleteComment(comment.id)
  } catch (e) {
    // Already gone, removed by the wall's owner or in another tab: what was asked for.
    if (e.status !== 404) {
      toast.add({
        severity: 'error',
        summary: t('profile.wall.error'),
        detail: e.message,
        life: 5000
      })
      return
    }
  }
  const { tree: next, removedCount } = treeWithoutComment(tree.value, comment.id)
  tree.value = next
  emit('count-changed', -removedCount)
}
</script>
