<template>
  <Dialog v-model:visible="visible" modal :header="t('profile.wall.likers_title')" class="w-full max-w-sm mx-4" @show="load">
    <div v-if="isLoading" class="flex justify-center py-4">
      <ProgressSpinner style="width: 2rem; height: 2rem" />
    </div>
    <p v-else-if="hasLoadError" class="text-sm text-surface-500 dark:text-surface-400 m-0" role="alert">
      {{ t('profile.wall.likers_error') }}
    </p>
    <ul v-else class="list-none m-0 p-0 flex flex-col gap-1">
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
    </ul>
  </Dialog>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import profileWallApi from '../../../api/user/profileWall.js'
import { getAvatarStyle } from '../../../utils/avatar.js'

const props = defineProps({
  postId: { type: String, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()

const likers = ref([])
const isLoading = ref(false)
const hasLoadError = ref(false)

// Fetched on every opening: the list changes as people like.
async function load() {
  isLoading.value = true
  hasLoadError.value = false
  try {
    likers.value = await profileWallApi.getLikers(props.postId)
  } catch {
    hasLoadError.value = true
  } finally {
    isLoading.value = false
  }
}
</script>
