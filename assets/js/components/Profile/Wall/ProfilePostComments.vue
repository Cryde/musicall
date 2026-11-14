<template>
  <section class="flex flex-col gap-3 pt-3 border-t border-surface-200 dark:border-surface-700" :aria-label="t('profile.wall.comments_title')">
    <div
      v-if="showsOrderToggle"
      role="group"
      :aria-label="t('profile.wall.comments_order.label')"
      class="flex gap-1.5"
    >
      <button
        v-for="option in COMMENT_ORDERS"
        :key="option.value"
        type="button"
        :aria-pressed="option.value === order"
        class="rounded-full px-3 py-1 text-xs font-semibold transition-colors cursor-pointer"
        :class="option.value === order
          ? 'bg-surface-900 text-surface-0 dark:bg-surface-0 dark:text-surface-900'
          : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'"
        @click="selectOrder(option.value)"
      >
        {{ t(option.labelKey) }}
      </button>
    </div>
    <!-- Newest first (#1331), a page reads down to the newest: the older comments load above it. -->
    <div v-if="nextCursor && !isLoading && loadsAbove(order)" class="flex flex-col items-start gap-1">
      <Button :label="t('profile.wall.older_comments')" text size="small" :loading="isLoadingMore" class="-ml-3" @click="loadMore" />
      <p v-if="hasMoreError" class="text-sm text-surface-500 dark:text-surface-400 m-0" role="alert">
        {{ t('profile.wall.comments_more_error') }}
      </p>
    </div>
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
          @toggle-like="toggleLike"
        />
        <ol v-if="comment.replies.length" class="list-none m-0 p-0 pl-10 flex flex-col gap-2">
          <li v-for="reply in comment.replies" :key="reply.id">
            <ProfilePostCommentItem :comment="reply" @delete="remove" @toggle-like="toggleLike" />
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

    <!-- Oldest first, the thread reads top down: the next comments load below it. -->
    <div v-if="nextCursor && !isLoading && !loadsAbove(order)" class="flex flex-col items-start gap-1">
      <Button :label="t('profile.wall.more_comments')" text size="small" :loading="isLoadingMore" class="-ml-3" @click="loadMore" />
      <p v-if="hasMoreError" class="text-sm text-surface-500 dark:text-surface-400 m-0" role="alert">
        {{ t('profile.wall.comments_more_error') }}
      </p>
    </div>

    <!-- Kept through a change of order, so a comment being written stays. -->
    <template v-if="hasLoaded">
      <ProfilePostCommentForm v-if="userSecurityStore.isAuthenticated" :post-id="postId" @posted="add" />
      <p v-else class="text-sm text-surface-500 dark:text-surface-400 m-0">
      <RouterLink :to="signInRouteFrom('app_login', route)" class="text-primary font-medium">{{ t('profile.wall.login') }}</RouterLink>
      {{ t('profile.wall.login_to_comment') }}
      </p>
    </template>
    <AuthRequiredModal v-model:visible="showAuthModal" :message="t('profile.wall.login_to_like_comment')" />
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import profileWallApi from '../../../api/user/profileWall.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import {
  COMMENT_ORDERS,
  commentTree,
  DEFAULT_COMMENT_ORDER,
  loadsAbove,
  nextCommentsCursor,
  treeWithComment,
  treeWithCommentLike,
  treeWithNextPage,
  treeWithoutComment
} from '../../../utils/profileWall.js'
import { signInRouteFrom } from '../../../utils/unauthenticatedRedirect.js'
import AuthRequiredModal from '../../Auth/AuthRequiredModal.vue'
import ProfilePostCommentForm from './ProfilePostCommentForm.vue'
import ProfilePostCommentItem from './ProfilePostCommentItem.vue'

const props = defineProps({
  postId: { type: String, required: true },
  /** A comment a notification links to: the first page reaches down to it, and it is scrolled to. */
  aroundCommentId: { type: String, default: null }
})

/** How far the post's comment count moved: a comment added, or a comment and its replies removed. */
const emit = defineEmits(['count-changed'])
const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const userSecurityStore = useUserSecurityStore()

const tree = ref([])
const order = ref(DEFAULT_COMMENT_ORDER)
const hasChosenOrder = ref(false)
const isLoading = ref(true)
const hasLoaded = ref(false)
const hasLoadError = ref(false)
const replyingTo = ref(null)
const nextCursor = ref(null)
const isLoadingMore = ref(false)
const hasMoreError = ref(false)
const showAuthModal = ref(false)
// The comments whose like is on its way: a second tap waits for the first answer.
const likingIds = new Set()
// Only the latest order may write: a page still on its way from the order left must not land in the new one.
let loadToken = 0

// Nothing to sort under one comment, unless the member already picked an order.
const showsOrderToggle = computed(
  () => hasChosenOrder.value || tree.value.length > 1 || nextCursor.value !== null
)

onMounted(async () => {
  await load(props.aroundCommentId)
  scrollToAroundComment()
})

async function load(around = null) {
  const token = ++loadToken
  isLoading.value = true
  hasLoadError.value = false
  isLoadingMore.value = false
  hasMoreError.value = false
  tree.value = []
  nextCursor.value = null
  replyingTo.value = null
  try {
    const page = await profileWallApi.getComments(props.postId, { order: order.value, around })
    if (token !== loadToken) return
    tree.value = commentTree(page, order.value)
    nextCursor.value = nextCommentsCursor(page)
  } catch {
    if (token === loadToken) hasLoadError.value = true
  } finally {
    if (token === loadToken) {
      isLoading.value = false
      hasLoaded.value = true
    }
  }
}

function selectOrder(value) {
  if (value === order.value) return
  order.value = value
  hasChosenOrder.value = true
  load()
}

async function scrollToAroundComment() {
  if (!props.aroundCommentId) return
  await nextTick()
  document
    .getElementById(`profile-comment-${props.aroundCommentId}`)
    ?.scrollIntoView({ block: 'center' })
}

async function loadMore() {
  if (!nextCursor.value || isLoadingMore.value) return
  const token = loadToken
  const pageOrder = order.value
  isLoadingMore.value = true
  hasMoreError.value = false
  try {
    const page = await profileWallApi.getComments(props.postId, {
      order: pageOrder,
      before: nextCursor.value
    })
    if (token !== loadToken) return
    tree.value = treeWithNextPage(tree.value, page, pageOrder)
    nextCursor.value = nextCommentsCursor(page)
  } catch {
    if (token === loadToken) hasMoreError.value = true
  } finally {
    if (token === loadToken) isLoadingMore.value = false
  }
}

// Shown at once, put back if the server refuses, as for a post.
async function toggleLike(comment) {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  if (likingIds.has(comment.id)) return
  const liked = !comment.has_liked
  tree.value = treeWithCommentLike(tree.value, comment.id, liked)
  likingIds.add(comment.id)
  try {
    if (liked) {
      const state = await profileWallApi.likeComment(comment.id)
      tree.value = treeWithCommentLike(tree.value, comment.id, true, state.like_count)
    } else {
      await profileWallApi.unlikeComment(comment.id)
    }
  } catch (e) {
    // A like already gone elsewhere is what was asked for.
    if (liked || e.status !== 404) {
      tree.value = treeWithCommentLike(tree.value, comment.id, !liked)
      toast.add({
        severity: 'error',
        summary: t('profile.wall.error'),
        detail: e.message,
        life: 5000
      })
    }
  } finally {
    likingIds.delete(comment.id)
  }
}

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
