<template>
  <div class="flex min-w-0 items-center gap-3">
    <Avatar
      v-if="userSecurityStore.profilePictureUrl"
      :image="userSecurityStore.profilePictureUrl"
      :pt="{ image: { alt: '' } }"
      shape="circle"
      :class="AVATAR"
      aria-hidden="true"
    />
    <Avatar
      v-else
      :label="username.charAt(0).toUpperCase()"
      :style="getAvatarStyle(username)"
      shape="circle"
      :class="AVATAR"
      aria-hidden="true"
    />
    <div class="flex min-w-0 flex-col">
      <span class="truncate font-semibold text-surface-900 dark:text-surface-0">{{ username }}</span>
      <RouterLink :to="profile.to" class="self-start text-sm font-semibold text-primary hover:underline">
        {{ profile.label }}
      </RouterLink>
    </div>
  </div>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import { computed } from 'vue'
import { useUserMenu } from '../../composables/useUserMenu.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'

// PrimeVue's own styles win over a size class, so the avatar is sized through its tokens.
const AVATAR = 'shrink-0 [--p-avatar-height:2.5rem] [--p-avatar-width:2.5rem]'

/** The member at the top of the phone menu: their picture, their name and the way to their profile. */
const userSecurityStore = useUserSecurityStore()
const { entries } = useUserMenu()

const username = computed(() => userSecurityStore.user?.username ?? '')
const profile = computed(() => entries.value.find((entry) => entry.key === 'profile'))
</script>
