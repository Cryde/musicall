<template>
  <template v-if="!userSecurityStore.isAuthenticatedLoading">
    <div class="flex items-center gap-2">
      <LocaleSwitcher v-if="CAN_CHOOSE_LOCALE" />
      <template v-if="userSecurityStore.isAuthenticated">
        <NavbarInbox />
        <Avatar
          v-if="userSecurityStore.profilePictureUrl"
          :image="userSecurityStore.profilePictureUrl"
          :pt="{ image: { alt: t('user_menu.avatar_alt', { username: userSecurityStore.user.username }) } }"
          class="cursor-pointer"
          shape="circle"
          role="button"
          :aria-label="t('user_menu.open', { username: userSecurityStore.user.username })"
          aria-haspopup="menu"
          @click="userMenu?.toggle($event)"
        />
        <Avatar
          v-else
          :label="userSecurityStore.user.username.charAt(0).toUpperCase()"
          :style="getAvatarStyle(userSecurityStore.user.username)"
          class="cursor-pointer"
          shape="circle"
          role="button"
          :aria-label="t('user_menu.open', { username: userSecurityStore.user.username })"
          aria-haspopup="menu"
          @click="userMenu?.toggle($event)"
        />
        <Menu ref="userMenu" :popup="true" :model="menuItems" />
      </template>
      <template v-else>
        <Button asChild v-slot="slotProps" severity="info" outlined>
          <RouterLink :to="{ name: 'app_login' }" :class="slotProps.class">{{ t('common.login') }}</RouterLink>
        </Button>
        <Button asChild v-slot="slotProps" severity="primary">
          <RouterLink :to="{ name: 'app_register' }" :class="slotProps.class">{{ t('common.register') }}</RouterLink>
        </Button>
      </template>
    </div>
  </template>
</template>

<script setup>
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { CAN_CHOOSE_LOCALE } from '../composables/useLocaleChoice.js'
import { useUserMenu } from '../composables/useUserMenu.js'
import { useUserSecurityStore } from '../store/user/security.js'
import { getAvatarStyle } from '../utils/avatar.js'
import LocaleSwitcher from './Global/LocaleSwitcher.vue'
import NavbarInbox from './Global/NavbarInbox.vue'

const { t } = useI18n()
const router = useRouter()
const userSecurityStore = useUserSecurityStore()
const { entries, logout } = useUserMenu()

const userMenu = ref(null)

// The rules between the profile, the member's content, the settings and the administration.
const SEPARATED_BEFORE = new Set(['announces', 'settings', 'admin'])

const menuItems = computed(() => [
  {
    label: userSecurityStore.user?.username,
    items: [
      ...entries.value.flatMap((entry) => [
        ...(SEPARATED_BEFORE.has(entry.key) ? [{ separator: true }] : []),
        { label: entry.label, icon: entry.icon, command: () => router.push(entry.to) }
      ]),
      { separator: true },
      { label: t('user_menu.logout'), icon: 'pi pi-sign-out', command: logout }
    ]
  }
])
</script>
