<template>
  <Dialog v-model:visible="visible" modal :header="t('profile.wall.likers_title')" class="w-full max-w-sm mx-4" @show="load">
    <div v-if="isLoading" class="flex justify-center py-4">
      <ProgressSpinner style="width: 2rem; height: 2rem" />
    </div>
    <p v-else-if="hasLoadError" class="text-sm text-surface-500 dark:text-surface-400 m-0" role="alert">
      {{ t('profile.wall.likers_error') }}
    </p>
    <!-- Scrolls on its own, so reaching its end loads the next likers (#1331). -->
    <ul v-else ref="listRef" class="list-none m-0 p-0 flex flex-col gap-1 max-h-[60vh] overflow-y-auto">
      <li v-for="liker in likers" :key="liker.username">
        <RouterLink
          :to="{ name: 'app_user_public_profile', params: { username: liker.username } }"
          class="flex items-center gap-3 p-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-900 dark:text-surface-0"
          @click="visible = false"
        >
          <Avatar
            v-if="liker.profile_picture_url"
            :image="liker.profile_picture_url"
            :pt="{ image: { alt: '' } }"
            shape="circle"
          />
          <Avatar
            v-else
            :label="liker.display_name.charAt(0).toUpperCase()"
            :style="getAvatarStyle(liker.username)"
            shape="circle"
          />
          <span class="font-medium truncate">{{ liker.display_name }}</span>
        </RouterLink>
      </li>
      <li v-if="isLoadingMore" class="flex justify-center py-2">
        <ProgressSpinner style="width: 1.5rem; height: 1.5rem" />
      </li>
      <li v-else-if="hasMoreError" class="flex flex-col items-center gap-1 py-2">
        <p class="text-sm text-surface-500 dark:text-surface-400 m-0" role="alert">{{ t('profile.wall.likers_more_error') }}</p>
        <Button :label="t('profile.wall.likers_retry')" text size="small" @click="loadMore" />
      </li>
    </ul>
  </Dialog>
</template>

<script setup>
import { useInfiniteScroll } from '@vueuse/core'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileWallApi from '../../../api/user/profileWall.js'
import { getAvatarStyle } from '../../../utils/avatar.js'
import { appendLikersPage, nextLikersCursor } from '../../../utils/profileWall.js'

const props = defineProps({
  postId: { type: String, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()

const likers = ref([])
const nextCursor = ref(null)
const isLoading = ref(false)
const hasLoadError = ref(false)
const isLoadingMore = ref(false)
const hasMoreError = ref(false)
const listRef = ref(null)
// Only the latest opening may write: a page asked for before the dialog was closed and opened again is dropped.
let loadToken = 0

// Fetched on every opening: the list changes as people like.
async function load() {
  const token = ++loadToken
  isLoading.value = true
  hasLoadError.value = false
  hasMoreError.value = false
  likers.value = []
  nextCursor.value = null
  try {
    const page = await profileWallApi.getLikers(props.postId)
    if (token !== loadToken) return
    likers.value = page
    nextCursor.value = nextLikersCursor(page)
  } catch {
    if (token === loadToken) hasLoadError.value = true
  } finally {
    if (token === loadToken) isLoading.value = false
  }
}

async function loadMore() {
  if (!nextCursor.value || isLoadingMore.value) return
  const token = loadToken
  isLoadingMore.value = true
  hasMoreError.value = false
  try {
    const page = await profileWallApi.getLikers(props.postId, nextCursor.value)
    if (token !== loadToken) return
    likers.value = appendLikersPage(likers.value, page)
    nextCursor.value = nextLikersCursor(page)
  } catch {
    if (token === loadToken) hasMoreError.value = true
  } finally {
    isLoadingMore.value = false
  }
}

// After a failed page, the next one waits for « Réessayer » rather than being asked for at every scroll.
useInfiniteScroll(listRef, loadMore, {
  distance: 100,
  canLoadMore: () => nextCursor.value !== null && !hasMoreError.value
})
</script>
