<template>
    <nav :aria-label="t('menu.aria_label')" class="relative flex items-center justify-between gap-8 px-6 md:px-12 lg:px-20 py-3 lg:py-4 bg-surface-0 dark:bg-surface-900">
      <div class="flex items-center gap-4">
        <RouterLink :to="{ name: 'app_home' }" class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2" :aria-label="t('common.musicall_home')">
          <img
            src="../../../image/logo-2.webp"
            :alt="t('common.musicall_logo')"
            width="106"
            height="16"
            class="h-4 w-auto"
          />
        </RouterLink>
      </div>

      <!-- Below lg the links wait in a drawer; a member's inboxes stay in the header with their counts.
           The negative margin lines the last icon up with the page's edge rather than its button's. -->
      <template v-if="isCompactNav">
        <div class="flex items-center gap-1 -mr-2.5">
          <NavbarInbox v-if="userSecurityStore.isAuthenticated" />
          <button
            ref="menuButton"
            type="button"
            class="flex items-center justify-center w-10 h-10 rounded-full cursor-pointer text-surface-900 dark:text-surface-100 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
            :aria-label="t('common.open_navigation_menu')"
            aria-haspopup="dialog"
            :aria-expanded="isMenuOpen"
            :aria-controls="isMenuOpen ? MENU_ID : undefined"
            @click="isMenuOpen = true"
          >
            <i class="pi pi-bars text-xl" aria-hidden="true" />
          </button>
        </div>
        <MobileNavDrawer :id="MENU_ID" v-model:visible="isMenuOpen" @after-hide="returnFocus" />
      </template>

      <div v-else class="flex flex-1 items-center justify-between">
        <div class="flex-1 flex items-start gap-4">
          <template v-for="(item, i) in navs" :key="i">
            <RouterLink
              :to="{name: item.to}"
              custom
              v-slot="{ isExactActive, href, navigate }"
            >
              <a
                v-bind="$attrs"
                :href="href"
                :aria-current="isExactActive ? 'page' : undefined"
                @click="navigate"
                :class="[
                  'flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors duration-150 border',
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
              class="relative"
              @mouseenter="showSearchDropdown"
              @mouseleave="hideSearchDropdown"
            >
              <button
                type="button"
                :class="[
                  'flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors duration-150 border',
                  isSearchActive
                  ? 'bg-surface-100 dark:bg-surface-800 border-surface-200 dark:border-surface-700'
                  : 'border-transparent hover:bg-surface-50 dark:hover:bg-surface-800 hover:border-surface-200 dark:hover:border-surface-700'
                ]"
                aria-haspopup="true"
                :aria-expanded="searchDropdownVisible"
                @click="toggleSearchDropdown"
              >
                <span class="font-medium">{{ t('menu.search') }}</span>
                <i class="pi pi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': searchDropdownVisible }" aria-hidden="true" />
              </button>
              <div
                v-show="searchDropdownVisible"
                class="absolute w-48 bg-surface-0 dark:bg-surface-900 left-0 top-full z-50 shadow-md rounded-lg overflow-hidden animate-fadein"
              >
                <ul class="list-none p-0 m-0">
                  <li>
                    <RouterLink
                      :to="{ name: 'app_search_musician' }"
                      class="flex items-center gap-3 px-4 py-3 text-surface-700 dark:text-surface-200 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                      @click="searchDropdownVisible = false"
                    >
                      <i class="pi pi-users" aria-hidden="true" />
                      <span class="font-medium">{{ t('menu.search_musicians') }}</span>
                    </RouterLink>
                  </li>
                  <li>
                    <RouterLink
                      :to="{ name: 'app_search_teacher' }"
                      class="flex items-center gap-3 px-4 py-3 text-surface-700 dark:text-surface-200 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                      @click="searchDropdownVisible = false"
                    >
                      <i class="pi pi-graduation-cap" aria-hidden="true" />
                      <span class="font-medium">{{ t('menu.search_teachers') }}</span>
                    </RouterLink>
                  </li>
                </ul>
              </div>
            </div>
          </template>
        </div>
        <AppNavbarUserCluster />
      </div>
    </nav>
</template>
<script setup>
import { useMediaQuery } from '@vueuse/core'
import { computed, onMounted, onUnmounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppNavbarUserCluster from '../../components/AppNavbarUserCluster.vue'
import MobileNavDrawer from '../../components/Global/MobileNavDrawer.vue'
import NavbarInbox from '../../components/Global/NavbarInbox.vue'
import { useUserSecurityStore } from '../../store/user/security.js'

const MENU_ID = 'mobile-menu'

const { t } = useI18n()
const router = useRouter()
const userSecurityStore = useUserSecurityStore()

// Tailwind's lg: the links collapse into the drawer below it, as they always have.
const isCompactNav = useMediaQuery('(width < 64rem)')
const isMenuOpen = ref(false)
const menuButton = useTemplateRef('menuButton')

// Widening past lg removes the drawer; it must not come back open when the window narrows again.
watch(isCompactNav, (isCompact) => {
  if (!isCompact) isMenuOpen.value = false
})

// PrimeVue's drawer does not hand focus back. After a link, the next page may have taken it already.
function returnFocus() {
  if (document.activeElement === document.body) {
    menuButton.value?.focus()
  }
}

const searchDropdownWrapper = ref(null)
const searchDropdownVisible = ref(false)
let searchDropdownHideTimeout = null

function showSearchDropdown() {
  clearTimeout(searchDropdownHideTimeout)
  searchDropdownVisible.value = true
}

function hideSearchDropdown() {
  searchDropdownHideTimeout = setTimeout(() => {
    searchDropdownVisible.value = false
  }, 100)
}

function toggleSearchDropdown() {
  searchDropdownVisible.value = !searchDropdownVisible.value
}

const isSearchActive = computed(() => {
  const currentRoute = router.currentRoute.value.name
  return currentRoute === 'app_search_musician' || currentRoute === 'app_search_teacher'
})

function handleClickOutside(event) {
  if (!searchDropdownVisible.value) return
  // searchDropdownWrapper is an array because it's inside v-for
  const wrapper = Array.isArray(searchDropdownWrapper.value)
    ? searchDropdownWrapper.value[0]
    : searchDropdownWrapper.value
  if (wrapper && !wrapper.contains(event.target)) {
    searchDropdownVisible.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const navs = computed(() => [
  { label: t('menu.home'), to: 'app_home' },
  { label: t('menu.publications'), to: 'app_publications' },
  { label: t('menu.courses'), to: 'app_course' },
  { label: t('menu.forum'), to: 'app_forum_index' },
  // Same entry either way, but a visitor without an account is sold the module rather than sent to
  // a login form. A member goes straight in, and app_band_index handles having no space yet.
  //
  // While the auth check is still in flight isAuthenticated is false for everyone, including a
  // returning member, so waiting on it keeps them from being sent to the pitch for their own module.
  {
    label: t('menu.band_space'),
    to:
      userSecurityStore.isAuthenticatedLoading || userSecurityStore.isAuthenticated
        ? 'app_band_index'
        : 'app_band_space_presentation'
  }
])
</script>
