<template>
  <section
    v-if="isOwner || (!isLoading && hasItems)"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm overflow-hidden"
    aria-labelledby="profile-wall-title"
  >
    <h2 id="profile-wall-title" class="sr-only">{{ t('profile.wall.title') }}</h2>
    <!-- A reading column while the profile is one wide column; #1317 gives the wall its own column. -->
    <div class="max-w-2xl mx-auto">
      <ProfilePostComposer
        v-if="isOwner"
        :display-name="displayName"
        :username="username"
        :picture-url="pictureUrl"
        class="p-4 border-b border-surface-200 dark:border-surface-700"
        @created="handleCreated"
      />

      <div v-if="isLoading" class="flex justify-center p-6">
        <ProgressSpinner style="width: 2rem; height: 2rem" />
      </div>
      <p v-else-if="hasLoadError" class="p-6 text-sm text-center text-surface-500 dark:text-surface-400 m-0">
        {{ t('profile.wall.load_error') }}
      </p>
      <p v-else-if="!hasItems" class="p-6 text-sm text-center text-surface-500 dark:text-surface-400 m-0">
        {{ t('profile.wall.empty_owner') }}
      </p>
      <!-- Shown beside a load error too: a post written meanwhile must not vanish. -->
      <ol v-if="!isLoading && hasItems" class="list-none m-0 p-0 divide-y divide-surface-200 dark:divide-surface-700">
        <li v-if="pinnedPost" :id="`post-${pinnedPost.id}`" class="scroll-mt-20">
          <ProfilePostItem
            :post="pinnedPost"
            :comments-open="pinnedPost.id === focusedPostId"
            @updated="handleUpdated"
            @deleted="handleDeleted"
          />
        </li>
        <li
          v-for="item in items"
          :id="item.type === 'post' ? `post-${item.post.id}` : null"
          :key="wallItemKey(item)"
          class="scroll-mt-20"
        >
          <ProfilePostItem
            v-if="item.type === 'post'"
            :post="item.post"
            :comments-open="item.post.id === focusedPostId"
            @updated="handleUpdated"
            @deleted="handleDeleted"
          />
          <ProfileWallActivity v-else :item="item" :display-name="displayName" />
        </li>
      </ol>

      <div v-if="nextCursor && !isLoading" class="flex flex-col items-center gap-2 p-4 border-t border-surface-200 dark:border-surface-700">
        <p v-if="hasMoreError" class="text-sm text-surface-500 dark:text-surface-400 m-0" role="alert">
          {{ t('profile.wall.more_error') }}
        </p>
        <Button :label="t('profile.wall.more')" text :loading="isLoadingMore" @click="loadMore" />
      </div>
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import profileWallApi from '../../../api/user/profileWall.js'
import {
  appendWallPage,
  nextWallCursor,
  postItem,
  wallAfterPinChange,
  wallItemKey
} from '../../../utils/profileWall.js'
import ProfilePostComposer from './ProfilePostComposer.vue'
import ProfilePostItem from './ProfilePostItem.vue'
import ProfileWallActivity from './ProfileWallActivity.vue'

const props = defineProps({
  username: { type: String, required: true },
  displayName: { type: String, required: true },
  pictureUrl: { type: String, default: null },
  isOwner: { type: Boolean, default: false }
})

const { t } = useI18n()
const route = useRoute()

const pinnedPost = ref(null)
// The posts and the activity lines, newest first, as the server merged them.
const items = ref([])
const nextCursor = ref(null)
const isLoading = ref(true)
const isLoadingMore = ref(false)
const hasLoadError = ref(false)
const hasMoreError = ref(false)
// Only the latest load may write: a quick change of profile must not show the previous wall.
let loadToken = 0

const hasItems = computed(() => pinnedPost.value !== null || items.value.length > 0)
// A notification links to `?post={id}`: that post opens its comments, when it is among those loaded.
const focusedPostId = computed(() =>
  typeof route.query.post === 'string' ? route.query.post : null
)

async function load() {
  const token = ++loadToken
  isLoading.value = true
  hasLoadError.value = false
  hasMoreError.value = false
  try {
    const [pinned, page] = await Promise.all([
      // Without its pinned post, the wall still reads.
      profileWallApi.getPinnedPosts(props.username).catch(() => []),
      profileWallApi.getWall(props.username)
    ])
    if (token !== loadToken) return
    pinnedPost.value = pinned[0] ?? null
    items.value = page
    nextCursor.value = nextWallCursor(page)
  } catch {
    if (token === loadToken) hasLoadError.value = true
  } finally {
    if (token === loadToken) isLoading.value = false
  }
}

async function loadMore() {
  if (!nextCursor.value) return
  const token = loadToken
  isLoadingMore.value = true
  hasMoreError.value = false
  try {
    const page = await profileWallApi.getWall(props.username, nextCursor.value)
    if (token !== loadToken) return
    items.value = appendWallPage(items.value, page)
    nextCursor.value = nextWallCursor(page)
  } catch {
    hasMoreError.value = true
  } finally {
    isLoadingMore.value = false
  }
}

function handleCreated(post) {
  items.value = [postItem(post), ...items.value]
}

function handleUpdated(post) {
  const wasPinned = pinnedPost.value?.id === post.id
  if (post.is_pinned !== wasPinned) {
    const next = wallAfterPinChange(
      { pinnedPost: pinnedPost.value, items: items.value, hasMore: nextCursor.value !== null },
      post
    )
    pinnedPost.value = next.pinnedPost
    items.value = next.items
    return
  }
  if (wasPinned) {
    pinnedPost.value = post
  } else {
    items.value = items.value.map((held) =>
      held.type === 'post' && held.post.id === post.id ? { ...held, post } : held
    )
  }
}

function handleDeleted(id) {
  if (pinnedPost.value?.id === id) {
    pinnedPost.value = null
  }
  items.value = items.value.filter((item) => !(item.type === 'post' && item.post.id === id))
}

async function scrollToFocusedPost() {
  if (!focusedPostId.value || isLoading.value) return
  await nextTick()
  document.getElementById(`post-${focusedPostId.value}`)?.scrollIntoView({ block: 'start' })
}

watch(() => props.username, load, { immediate: true })
watch([focusedPostId, isLoading], scrollToFocusedPost)
</script>
