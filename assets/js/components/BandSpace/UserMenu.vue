<template>
  <template v-if="!userSecurityStore.isAuthenticatedLoading">
    <div v-if="userSecurityStore.isAuthenticated">
      <Avatar
        :label="userInitial"
        class="mr-2 cursor-pointer"
        shape="circle"
        @click="toggle"
      />
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

const userSecurityStore = useUserSecurityStore()

const menuRef = ref(null)

const userInitial = computed(() =>
  userSecurityStore.user?.username?.charAt(0)?.toUpperCase() || '?'
)

const menuItems = computed(() => [{
  label: userSecurityStore.user?.username,
  items: [
    {
      label: 'Se déconnecter',
      icon: 'pi pi-sign-out',
      command: () => userSecurityStore.logout()
    }
  ]
}])

function toggle(event) {
  menuRef.value?.toggle(event)
}
</script>
