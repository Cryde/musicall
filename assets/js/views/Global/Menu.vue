<template>
    <nav class="relative flex items-center justify-between gap-8 px-8 lg:px-20 py-4 bg-surface-0 dark:bg-surface-900">
      <div class="flex items-center gap-4">
        <div class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2">
          <img
            src="../../../image/logo.png"
            alt="Logo"
            class="h-4 w-auto"
          />
        </div>
      </div>
      <span
        v-styleclass="{
            selector: '@next',
            enterFromClass: 'hidden',
            enterActiveClass: 'animate-fadein',
            leaveToClass: 'hidden',
            leaveActiveClass: 'animate-fadeout',
            hideOnOutsideClick: true
          }"
        class="cursor-pointer block lg:hidden text-surface-900 dark:text-surface-100"
      >
        <i class="pi pi-bars text-xl! leading-normal!"/>
      </span>

      <div
          class="hidden lg:flex flex-1 items-center justify-between absolute lg:static w-full bg-surface-0 dark:bg-surface-900 left-0 top-full z-10 shadow lg:shadow-none border lg:border-0 border-surface-800"
      >
        <div class="flex-1 flex items-start gap-4 px-6 lg:px-0 py-4 lg:py-0 flex-col lg:flex-row">
          <RouterLink
            v-for="(item, i) in navs"
            :key="i"
            :to="{name: item.to}"
            custom
            v-slot="{ isExactActive, href, navigate }"
          >
            <a
              v-bind="$attrs"
              :href="href"
              @click="navigate"
              :class="[
                'flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors duration-150 border w-full lg:w-auto',
                isExactActive
                ? 'bg-surface-100 dark:bg-surface-800 border-surface-200 dark:border-surface-700'
                : 'border-transparent hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-200 dark:hover:border-surface-700'
              ]"
            >
              <span class="font-medium">{{ item.label }}</span>
            </a>
          </RouterLink>
        </div>
          <template v-if="!userSecurityStore.isAuthenticatedLoading">
          <div class="flex items-center border-t lg:border-t-0 border-surface py-4 lg:py-0 mt-4 lg:mt-0 gap-2 px-6 lg:px-0">
              <template v-if="userSecurityStore.isAuthenticated">
                  <RouterLink :to="{ name: 'app_messages' }" class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors">
                      <OverlayBadge v-if="notificationStore.unreadMessages > 0"
                                    :value="notificationStore.unreadMessages" severity="danger" size="small">
                          <i class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" />
                      </OverlayBadge>
                      <i v-else class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" />
                  </RouterLink>
                  <Avatar
                    v-if="userSecurityStore.profilePictureUrl"
                    :image="userSecurityStore.profilePictureUrl"
                    class="cursor-pointer"
                    shape="circle"
                    @click="$refs.userMenu.toggle($event)"
                  />
                  <Avatar
                    v-else
                    :label="userSecurityStore.user.username.charAt(0).toUpperCase()"
                    class="cursor-pointer"
                    shape="circle"
                    @click="$refs.userMenu.toggle($event)"
                  />
                  <Menu ref="userMenu" :popup="true" :model="menuItems" />
              </template>
              <template v-else>
                  <Button asChild v-slot="slotProps" severity="info" text>
                      <RouterLink :to="{name: 'app_login'}" :class="slotProps.class">Se connecter</RouterLink>
                  </Button>
                  <Button asChild v-slot="slotProps" severity="info">
                      <RouterLink :to="{name: 'app_register'}" :class="slotProps.class">S'inscrire</RouterLink>
                  </Button>
              </template>
          </div>
          </template>
      </div>
    </nav>
</template>
<script setup>
import Menu from 'primevue/menu'
import OverlayBadge from 'primevue/overlaybadge'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserSecurityStore } from '../../store/user/security.js'

const router = useRouter()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()

onMounted(async () => {
  if (userSecurityStore.isAuthenticated) {
    await notificationStore.loadNotifications()
  }
})

const menuItems = computed(() => [
  {
    label: userSecurityStore.user?.username,
    items: [
      {
        label: 'Mes annonces',
        icon: 'pi pi-megaphone',
        command: () => {
          router.push({ name: 'app_user_announces' })
        }
      },
      {
        label: 'Mes publications',
        icon: 'pi pi-file-edit',
        command: () => {
          router.push({ name: 'app_user_publications' })
        }
      },
      {
        label: 'Parametres',
        icon: 'pi pi-cog',
        command: () => {
          router.push({ name: 'app_user_settings' })
        }
      },
      {
        separator: true
      },
      {
        label: 'Se deconnecter',
        icon: 'pi pi-sign-out',
        command: () => {
          userSecurityStore.logout()
        }
      }
    ]
  }
])

const navs = ref([
  {
    label: 'Home',
    to: 'app_home'
  },
  {
    label: 'Publications',
    to: 'app_publications'
  },
  {
    label: 'Cours',
    to: 'app_course'
  },
  {
    label: 'Recherche',
    to: 'app_search_musician'
  },
  {
    label: 'Forum',
    to: 'app_forum_index'
  }
])
</script>
