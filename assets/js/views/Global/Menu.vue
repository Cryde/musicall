<template>
    <nav class="relative flex items-center justify-between gap-8 px-8 lg:px-20 py-4 bg-surface-0 dark:bg-surface-900">
      <div class="flex items-center gap-4">
        <RouterLink :to="{ name: 'app_home' }" class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2" aria-label="Accueil MusicAll">
          <img
            src="../../../image/logo-2.png"
            alt="Logo MusicAll"
            class="h-4 w-auto"
          />
        </RouterLink>
      </div>
      <button
        v-styleclass="{
            selector: '@next',
            enterFromClass: 'hidden',
            enterActiveClass: 'animate-fadein',
            leaveToClass: 'hidden',
            leaveActiveClass: 'animate-fadeout',
            hideOnOutsideClick: true
          }"
        class="cursor-pointer block lg:hidden text-surface-900 dark:text-surface-100 bg-transparent border-0 p-0"
        aria-label="Ouvrir le menu de navigation"
        aria-expanded="false"
        aria-controls="mobile-menu"
      >
        <i class="pi pi-bars text-xl! leading-normal!" aria-hidden="true" />
      </button>

      <div
          ref="mobileMenu"
          class="hidden lg:flex flex-1 items-center justify-between absolute lg:static w-full bg-surface-0 dark:bg-surface-900 left-0 top-full z-50 shadow lg:shadow-none border lg:border-0 border-surface-800"
      >
        <div class="flex-1 flex items-start gap-4 px-6 lg:px-0 py-4 lg:py-0 flex-col lg:flex-row">
          <template v-for="(item, i) in navs" :key="i">
            <RouterLink
              :to="{name: item.to}"
              custom
              v-slot="{ isExactActive, href, navigate }"
            >
              <a
                v-bind="$attrs"
                :href="href"
                @click="onNavClick($event, navigate)"
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

            <!-- Recherche dropdown after Cours -->
            <div
              v-if="item.to === 'app_course'"
              ref="searchDropdownWrapper"
              class="relative w-full lg:w-auto"
              @mouseenter="showSearchDropdown"
              @mouseleave="hideSearchDropdown"
            >
              <a
                :class="[
                  'flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors duration-150 border w-full lg:w-auto',
                  isSearchActive
                  ? 'bg-surface-100 dark:bg-surface-800 border-surface-200 dark:border-surface-700'
                  : 'border-transparent hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-200 dark:hover:border-surface-700'
                ]"
                aria-haspopup="true"
                :aria-expanded="searchDropdownVisible"
                @click="toggleSearchDropdown"
              >
                <span class="font-medium">Recherche</span>
                <i class="pi pi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': searchDropdownVisible }" aria-hidden="true" />
              </a>
              <div
                v-show="searchDropdownVisible"
                class="lg:absolute w-full lg:w-48 bg-surface-0 dark:bg-surface-900 left-0 top-full z-50 shadow-md rounded-lg overflow-hidden animate-fadein"
              >
                <ul class="list-none p-0 m-0">
                  <li>
                    <RouterLink
                      :to="{ name: 'app_search_musician' }"
                      class="flex items-center gap-3 px-4 py-3 text-surface-700 dark:text-surface-200 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                      @click="onSearchItemClick"
                    >
                      <i class="pi pi-users" aria-hidden="true" />
                      <span class="font-medium">Musiciens</span>
                    </RouterLink>
                  </li>
                  <li>
                    <RouterLink
                      :to="{ name: 'app_search_teacher' }"
                      class="flex items-center gap-3 px-4 py-3 text-surface-700 dark:text-surface-200 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                      @click="onSearchItemClick"
                    >
                      <i class="pi pi-graduation-cap" aria-hidden="true" />
                      <span class="font-medium">Professeurs</span>
                    </RouterLink>
                  </li>
                </ul>
              </div>
            </div>
          </template>
        </div>
          <template v-if="!userSecurityStore.isAuthenticatedLoading">
          <div class="flex items-center border-t lg:border-t-0 border-surface py-4 lg:py-0 mt-4 lg:mt-0 gap-2 px-6 lg:px-0">
              <template v-if="userSecurityStore.isAuthenticated">
                  <RouterLink
                    :to="{ name: 'app_messages' }"
                    class="flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
                    :aria-label="notificationStore.unreadMessages > 0 ? `Messages (${notificationStore.unreadMessages} non lus)` : 'Messages'"
                    @click="closeMobileMenu"
                  >
                      <OverlayBadge v-if="notificationStore.unreadMessages > 0"
                                    :value="notificationStore.unreadMessages" severity="danger" size="small">
                          <i class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
                      </OverlayBadge>
                      <i v-else class="pi pi-envelope text-xl text-surface-600 dark:text-surface-300" aria-hidden="true" />
                  </RouterLink>
                  <Avatar
                    v-if="userSecurityStore.profilePictureUrl"
                    :image="userSecurityStore.profilePictureUrl"
                    :pt="{ image: { alt: `Photo de ${userSecurityStore.user.username}` } }"
                    class="cursor-pointer"
                    shape="circle"
                    role="button"
                    :aria-label="`Menu utilisateur de ${userSecurityStore.user.username}`"
                    aria-haspopup="menu"
                    @click="$refs.userMenu.toggle($event)"
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
                    @click="$refs.userMenu.toggle($event)"
                  />
                  <Menu ref="userMenu" :popup="true" :model="menuItems" />
              </template>
              <template v-else>
                  <Button asChild v-slot="slotProps" severity="info" outlined>
                      <RouterLink :to="{name: 'app_login'}" :class="slotProps.class" @click="closeMobileMenu">Se connecter</RouterLink>
                  </Button>
                  <Button asChild v-slot="slotProps" severity="primary">
                      <RouterLink :to="{name: 'app_register'}" :class="slotProps.class" @click="closeMobileMenu">S'inscrire</RouterLink>
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
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../store/notification/notification.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'

const router = useRouter()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()

const mobileMenu = ref(null)
const searchDropdownWrapper = ref(null)
const searchDropdownVisible = ref(false)
let searchDropdownHideTimeout = null

function closeMobileMenu() {
  if (mobileMenu.value && window.innerWidth < 1024) {
    mobileMenu.value.classList.add('hidden')
  }
}

function onNavClick(event, navigate) {
  navigate(event)
  closeMobileMenu()
}

// Search dropdown functions
function showSearchDropdown() {
  // Only for desktop hover
  if (window.innerWidth < 1024) return
  clearTimeout(searchDropdownHideTimeout)
  searchDropdownVisible.value = true
}

function hideSearchDropdown() {
  // Only for desktop hover
  if (window.innerWidth < 1024) return
  searchDropdownHideTimeout = setTimeout(() => {
    searchDropdownVisible.value = false
  }, 100)
}

function toggleSearchDropdown() {
  searchDropdownVisible.value = !searchDropdownVisible.value
}

function onSearchItemClick() {
  searchDropdownVisible.value = false
  closeMobileMenu()
}

const isSearchActive = computed(() => {
  const currentRoute = router.currentRoute.value.name
  return currentRoute === 'app_search_musician' || currentRoute === 'app_search_teacher'
})

function handleClickOutside(event) {
  if (!searchDropdownVisible.value) return
  // searchDropdownWrapper is an array because it's inside v-for
  const wrapper = Array.isArray(searchDropdownWrapper.value) ? searchDropdownWrapper.value[0] : searchDropdownWrapper.value
  if (wrapper && !wrapper.contains(event.target)) {
    searchDropdownVisible.value = false
  }
}

onMounted(async () => {
  if (userSecurityStore.isAuthenticated) {
    await notificationStore.loadNotifications()
  }
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const menuItems = computed(() => {
  const items = [
    {
      label: 'Mon profil',
      icon: 'pi pi-user',
      command: () => {
        router.push({ name: 'app_user_public_profile', params: { username: userSecurityStore.user.username } })
      }
    },
    {
      separator: true
    },
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
      label: 'Mes cours',
      icon: 'pi pi-book',
      command: () => {
        router.push({ name: 'app_user_courses' })
      }
    },
    {
      label: 'Mes photos',
      icon: 'pi pi-images',
      command: () => {
        router.push({ name: 'app_user_galleries' })
      }
    },
    {
      separator: true
    },
    {
      label: 'Paramètres',
      icon: 'pi pi-cog',
      command: () => {
        router.push({ name: 'app_user_settings' })
      }
    }
  ]

  if (userSecurityStore.isAdmin) {
    items.push(
      { separator: true },
      {
        label: 'Administration',
        icon: 'pi pi-shield',
        command: () => {
          router.push({ name: 'admin_dashboard' })
        }
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

const navs = ref([
  {
    label: 'Accueil',
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
    label: 'Forum',
    to: 'app_forum_index'
  }
])
</script>
