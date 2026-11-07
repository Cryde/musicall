<template>
  <section
    ref="sectionRef"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm scroll-mt-20 lg:scroll-mt-6"
    aria-labelledby="profile-wall-title"
    :aria-busy="isLoading"
  >
    <h2 id="profile-wall-title" class="sr-only">{{ t('profile.wall.title') }}</h2>
    <ProfilePostComposer
      v-if="isOwner && showsPosts(filter)"
      :display-name="displayName"
      :username="username"
      :picture-url="pictureUrl"
      class="p-4 border-b border-surface-200 dark:border-surface-700"
      @created="handleCreated"
    />

    <!-- Sticks under the phone header, which stays on screen below lg. -->
    <div
      role="group"
      :aria-label="t('profile.wall.filter.label')"
      class="flex gap-1.5 overflow-x-auto px-4 py-3 border-b border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 max-lg:sticky max-lg:top-16 max-lg:z-10"
      :class="{ 'rounded-t-xl': !(isOwner && showsPosts(filter)) }"
    >
      <button
        v-for="option in WALL_FILTERS"
        :key="option.labelKey"
        type="button"
        :aria-pressed="option === filter"
        class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold transition-colors cursor-pointer"
        :class="option === filter
          ? 'bg-surface-900 text-surface-0 dark:bg-surface-0 dark:text-surface-900'
          : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'"
        @click="selectFilter(option)"
      >
        {{ t(option.labelKey) }}
      </button>
    </div>

    <!-- « Médias » is a grid of the profile's media (#1317), which the page already holds: no wall page to fetch. -->
    <ProfileMediaGrid
      v-if="isMediaGrid"
      :media="media"
      :is-owner="isOwner"
      :owner-username="username"
      :manage-route="manageMediaRoute"
    />
    <template v-else>
    <!-- Some height while a filter loads, so the page does not jump under the sticky pills. -->
    <div v-if="isLoading" class="flex justify-center items-start p-6 min-h-48">
      <ProgressSpinner style="width: 2rem; height: 2rem" />
    </div>
    <p v-else-if="hasLoadError" class="p-6 text-sm text-center text-surface-500 dark:text-surface-400 m-0">
      {{ t('profile.wall.load_error') }}
    </p>
    <p v-else-if="!hasItems" class="p-6 text-sm text-center text-surface-500 dark:text-surface-400 m-0">
      {{ emptyText }}
    </p>
    <!-- Shown beside a load error too: a post written meanwhile must not vanish. -->
    <ol v-if="!isLoading && hasItems" class="list-none m-0 p-0 divide-y divide-surface-200 dark:divide-surface-700">
      <li v-if="pinnedPost" :id="`post-${pinnedPost.id}`" class="scroll-mt-32 lg:scroll-mt-6">
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
        class="scroll-mt-32 lg:scroll-mt-6"
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
    </template>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import profileWallApi from '../../../api/user/profileWall.js'
import {
  appendWallPage,
  nextWallCursor,
  postItem,
  showsPosts,
  WALL_FILTERS,
  wallAfterPinChange,
  wallFilterFromQuery,
  wallItemKey,
  wallNeedsScrolling
} from '../../../utils/profileWall.js'
import ProfileMediaGrid from './ProfileMediaGrid.vue'
import ProfilePostComposer from './ProfilePostComposer.vue'
import ProfilePostItem from './ProfilePostItem.vue'
import ProfileWallActivity from './ProfileWallActivity.vue'

const props = defineProps({
  username: { type: String, required: true },
  displayName: { type: String, required: true },
  pictureUrl: { type: String, default: null },
  isOwner: { type: Boolean, default: false },
  /** The profile's musician and teacher media, for the « Médias » grid. */
  media: { type: Array, default: () => [] },
  /** Where the owner manages them. */
  manageMediaRoute: { type: Object, default: null }
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

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
// The filter lives in `?voir=` (#1317), so a filter can be linked to.
const filter = computed(() => wallFilterFromQuery(route.query.voir))
const isMediaGrid = computed(() => filter.value.type === 'media')
const sectionRef = ref(null)
const emptyText = computed(() => {
  if (filter.value.emptyKey) return t(filter.value.emptyKey)
  return props.isOwner
    ? t('profile.wall.empty_owner')
    : t('profile.wall.empty.visitor', { name: props.displayName })
})
// A notification links to `?post={id}`: that post opens its comments, when it is among those loaded.
const focusedPostId = computed(() =>
  typeof route.query.post === 'string' ? route.query.post : null
)

async function load() {
  const token = ++loadToken
  isLoading.value = true
  hasLoadError.value = false
  hasMoreError.value = false
  // Another filter or member: what was shown must not linger under an error.
  pinnedPost.value = null
  items.value = []
  nextCursor.value = null
  if (isMediaGrid.value) {
    isLoading.value = false
    return
  }
  try {
    const [pinned, page] = await Promise.all([
      // Without its pinned post, the wall still reads.
      showsPosts(filter.value) ? profileWallApi.getPinnedPosts(props.username).catch(() => []) : [],
      profileWallApi.getWall(props.username, null, filter.value.type)
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
    const page = await profileWallApi.getWall(props.username, nextCursor.value, filter.value.type)
    if (token !== loadToken) return
    items.value = appendWallPage(items.value, page)
    nextCursor.value = nextWallCursor(page)
  } catch {
    hasMoreError.value = true
  } finally {
    isLoadingMore.value = false
  }
}

function selectFilter(option) {
  if (option === filter.value) return
  // The notified post (`?post=`) is not scrolled back to after every switch.
  const { post: _notifiedPost, ...query } = route.query
  router.replace({ query: { ...query, voir: option.query ?? undefined } })
}

function handleCreated(post) {
  if (!showsPosts(filter.value)) return
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

watch([() => props.username, filter], load, { immediate: true })
// A link elsewhere on the page (« Ce que je fais ») can change the filter: the wall comes into view when it is
// still below the middle of the screen, as on a phone.
watch(filter, async () => {
  await nextTick()
  const top = sectionRef.value?.getBoundingClientRect().top
  if (top !== undefined && wallNeedsScrolling(top, window.innerHeight)) {
    sectionRef.value.scrollIntoView({ block: 'start' })
  }
})
watch([focusedPostId, isLoading], scrollToFocusedPost)
</script>
