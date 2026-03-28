<template>
  <template v-if="!userSecurityStore.isAuthenticatedLoading">
    <div v-if="userSecurityStore.isAuthenticated" class="flex items-center gap-3 cursor-pointer" @click="toggle">
      <Avatar
        v-if="userSecurityStore.profilePictureUrl"
        :image="userSecurityStore.profilePictureUrl"
        shape="circle"
      />
      <Avatar
        v-else
        :label="userInitial"
        :style="getAvatarStyle(userSecurityStore.user?.username)"
        shape="circle"
      />
      <span class="text-sm font-medium text-surface-700 dark:text-surface-200 lg:hidden">
        {{ userSecurityStore.user?.username }}
      </span>
      <Menu ref="menuRef" :popup="true" :model="menuItems" />
    </div>
    <div v-else class="flex border-t lg:border-t-0 border-surface py-4 lg:py-0 mt-4 lg:mt-0 gap-4">
      <Button asChild v-slot="slotProps" severity="info" text>
        <RouterLink :to="{ name: 'app_login' }" :class="slotProps.class">
          Se connecter
        </RouterLink>
      </Button>
    </div>
  </template>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref } from 'vue'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'

const userSecurityStore = useUserSecurityStore()

const menuRef = ref(null)

const userInitial = computed(
  () => userSecurityStore.user?.username?.charAt(0)?.toUpperCase() || '?'
)

const menuItems = computed(() => [
  {
    label: userSecurityStore.user?.username,
    items: [
      {
        label: 'Se déconnecter',
        icon: 'pi pi-sign-out',
        command: () => userSecurityStore.logout()
      }
    ]
  }
])

function toggle(event) {
  menuRef.value?.toggle(event)
}
</script>
