<template>
  <template v-if="!userSecurityStore.isAuthenticatedLoading">
    <div class="flex items-center border-t lg:border-t-0 border-surface py-4 lg:py-0 mt-4 lg:mt-0 gap-2 px-6 lg:px-0">
      <template v-if="userSecurityStore.isAuthenticated">
        <RouterLink
          :to="{ name: 'app_messages' }"
          class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
          :aria-label="notificationStore.unreadMessages > 0 ? `Messages (${notificationStore.unreadMessages} non lus)` : 'Messages'"
          @click="emit('navigate')"
        >
          <OverlayBadge
            v-if="notificationStore.unreadMessages > 0"
            :value="notificationStore.unreadMessages"
            severity="danger"
            size="small"
          >
            <i class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
          </OverlayBadge>
          <i v-else class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
        </RouterLink>
        <NotificationBell @navigate="emit('navigate')" />
        <Avatar
          v-if="userSecurityStore.profilePictureUrl"
          :image="userSecurityStore.profilePictureUrl"
          :pt="{ image: { alt: `Photo de ${userSecurityStore.user.username}` } }"
          class="cursor-pointer"
          shape="circle"
          role="button"
          :aria-label="`Menu utilisateur de ${userSecurityStore.user.username}`"
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
          :aria-label="`Menu utilisateur de ${userSecurityStore.user.username}`"
          aria-haspopup="menu"
          @click="userMenu?.toggle($event)"
        />
        <Menu ref="userMenu" :popup="true" :model="menuItems" />
      </template>
      <template v-else>
        <Button asChild v-slot="slotProps" severity="info" outlined>
          <RouterLink :to="{ name: 'app_login' }" :class="slotProps.class" @click="emit('navigate')">Se connecter</RouterLink>
        </Button>
        <Button asChild v-slot="slotProps" severity="primary">
          <RouterLink :to="{ name: 'app_register' }" :class="slotProps.class" @click="emit('navigate')">S'inscrire</RouterLink>
        </Button>
      </template>
    </div>
  </template>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import OverlayBadge from 'primevue/overlaybadge'
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../store/notification/notification.js'
import { useUserSecurityStore } from '../store/user/security.js'
import { getAvatarStyle } from '../utils/avatar.js'

// Authenticated-only (guarded by v-if) — load the bell + its notification machinery
// on demand so anonymous visitors never download it.
const NotificationBell = defineAsyncComponent(() => import('./Notification/NotificationBell.vue'))

const emit = defineEmits(['navigate'])

const router = useRouter()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()

const userMenu = ref(null)

onMounted(async () => {
  if (userSecurityStore.isAuthenticated) {
    await notificationStore.loadNotifications()
  }
})

const menuItems = computed(() => {
  const items = [
    {
      label: 'Mon profil',
      icon: 'pi pi-user',
      command: () => {
        router.push({
          name: 'app_user_public_profile',
          params: { username: userSecurityStore.user.username }
        })
      }
    },
    { separator: true },
    {
      label: 'Mes annonces',
      icon: 'pi pi-megaphone',
      command: () => router.push({ name: 'app_user_announces' })
    },
    {
      label: 'Mes publications',
      icon: 'pi pi-file-edit',
      command: () => router.push({ name: 'app_user_publications' })
    },
    {
      label: 'Mes cours',
      icon: 'pi pi-book',
      command: () => router.push({ name: 'app_user_courses' })
    },
    {
      label: 'Mes photos',
      icon: 'pi pi-images',
      command: () => router.push({ name: 'app_user_galleries' })
    },
    { separator: true },
    {
      label: 'Paramètres',
      icon: 'pi pi-cog',
      command: () => router.push({ name: 'app_user_settings' })
    }
  ]

  if (userSecurityStore.isAdmin) {
    items.push(
      { separator: true },
      {
        label: 'Administration',
        icon: 'pi pi-shield',
        command: () => router.push({ name: 'admin_dashboard' })
      }
    )
  }

  items.push(
    { separator: true },
    {
      label: 'Se déconnecter',
      icon: 'pi pi-sign-out',
      command: () => {
        trackUmamiEvent('user-logout')
        userSecurityStore.logout()
      }
    }
  )

  return [
    {
      label: userSecurityStore.user?.username,
      items
    }
  ]
})
</script>
