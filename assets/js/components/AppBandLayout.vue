<template>
  <div class="relative min-h-[50rem] bg-surface-50 dark:bg-surface-950">
    <Toast />
    <template v-if="isLoading">
      <div class="flex items-center justify-center min-h-[50rem]">
        <i class="pi pi-spin pi-spinner text-4xl"></i>
      </div>
    </template>
    <template v-else-if="hasError">
      <div class="flex flex-col items-center justify-center min-h-[50rem] gap-4">
        <i class="pi pi-exclamation-circle text-6xl text-red-500"></i>
        <p class="text-surface-600 dark:text-surface-400">
          {{ t('band_space.layout.load_error') }}
        </p>
        <Button :label="t('band_space.layout.retry')" icon="pi pi-refresh" @click="retry" />
      </div>
    </template>
    <template v-else>
      <SkipLink />
      <MenuBand v-model:mobile-nav-open="mobileNavOpen" @open-search="paletteVisible = true" />
      <CommandPalette v-model:visible="paletteVisible" />
      <div class="flex">
        <aside
          class="hidden lg:block w-[var(--band-sidebar-width)] shrink-0 sticky top-16 h-[calc(100dvh-4rem)] bg-surface-0 dark:bg-surface-900 border-r border-surface-200 dark:border-surface-700 self-start transition-[width] duration-150"
        >
          <BandSidebar
            v-model:collapsed="sidebarCollapsed"
            :disabled="bandSpaceStore.isCreating"
            :show-toggle="true"
          />
        </aside>
        <main id="main-content" tabindex="-1" class="flex-1 min-w-0 bg-surface-200 dark:bg-surface-950">
          <div class="px-6 py-8 md:px-12 lg:pl-8 lg:pr-20 flex flex-col gap-8">
            <!-- Pending deletion is shown on every module, not only in the settings: the grace period is
                 the members' window to retrieve their files, so they have to see it wherever they are. -->
            <div
              v-if="deletionScheduledFor"
              role="alert"
              class="flex items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-300"
            >
              <i class="pi pi-exclamation-triangle mt-0.5" aria-hidden="true" />
              <i18n-t keypath="band_space.layout.deletion_banner" tag="p" class="flex-1 min-w-0">
                <template #date>
                  <strong>{{ formatDateLong(deletionScheduledFor) }}</strong>
                </template>
                <template #link>
                  <RouterLink
                    :to="{ name: BAND_SPACE_ROUTES.PARAMETERS, params: { id: route.params.id }, query: { section: 'danger' } }"
                    class="underline"
                  >
                    {{ t('band_space.layout.manage_deletion') }}
                  </RouterLink>
                </template>
              </i18n-t>
            </div>
            <!-- Force remount on space switch so module views never retain
                 another space's bandSpaceId or store state. -->
            <router-view :key="route.params.id" />
          </div>
        </main>
      </div>
      <Drawer
        v-model:visible="mobileNavOpen"
        position="left"
        :style="{ width: '18rem' }"
      >
        <template #header>
          <span class="font-semibold">{{ t('band_space.layout.navigation') }}</span>
        </template>
        <div class="flex flex-col h-full">
          <div
            class="flex flex-col gap-2 p-3 border-b border-surface-200 dark:border-surface-700 shrink-0"
          >
            <BandSpaceSelector class="w-full" @navigate="mobileNavOpen = false" />
            <RouterLink :to="{ name: 'app_home' }" custom v-slot="{ href, navigate }">
              <a
                :href="href"
                @click="(e) => { navigate(e); mobileNavOpen = false }"
                class="flex items-center text-xs gap-2 px-3 py-2 rounded-lg text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800"
              >
                <i class="pi pi-arrow-left text-xs" aria-hidden="true"></i>
                <span class="font-medium">{{ t('band_menu.back_to_musicall') }}</span>
              </a>
            </RouterLink>
          </div>
          <div class="flex-1 min-h-0">
            <BandSidebar
              :disabled="bandSpaceStore.isCreating"
              @navigate="mobileNavOpen = false"
            >
              <template #after-work>
                <div class="mt-2">
                  <button
                    v-styleclass="{
                      selector: '@next',
                      enterFromClass: 'hidden',
                      enterActiveClass: 'animate-slidedown',
                      leaveToClass: 'hidden',
                      leaveActiveClass: 'animate-slideup'
                    }"
                    type="button"
                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors duration-150"
                  >
                    <i class="pi pi-user text-base shrink-0" aria-hidden="true"></i>
                    <span class="font-medium truncate">
                      {{ userSecurityStore.user?.username ?? t('band_space.layout.my_account') }}
                    </span>
                    <i class="pi pi-chevron-down text-sm ml-auto" aria-hidden="true"></i>
                  </button>
                  <!-- Mirror of AppNavbarUserCluster's menuItems — keep in sync. -->
                  <ul class="hidden list-none p-0 m-0 mt-1 flex flex-col gap-1 pl-3">
                    <li
                      v-for="entry in userMenuEntries"
                      :key="entry.label"
                    >
                      <button
                        type="button"
                        @click="() => handleUserMenuClick(entry)"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer text-sm"
                      >
                        <i :class="['pi', entry.icon, 'text-sm shrink-0']" aria-hidden="true"></i>
                        <span class="truncate">{{ entry.label }}</span>
                      </button>
                    </li>
                  </ul>
                </div>
              </template>

              <template #above-settings>
                <RouterLink :to="{ name: 'app_messages' }" custom v-slot="{ href, navigate }">
                  <a
                    :href="href"
                    @click="(e) => { navigate(e); mobileNavOpen = false }"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-surface-700 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 cursor-pointer"
                  >
                    <i class="pi pi-envelope text-base shrink-0" aria-hidden="true"></i>
                    <span class="font-medium truncate">{{ t('user_menu.messages') }}</span>
                    <Badge
                      v-if="notificationStore.unreadMessages > 0"
                      :value="notificationStore.unreadMessages"
                      severity="danger"
                      class="ml-auto"
                    />
                  </a>
                </RouterLink>
                <!-- The header's language switcher, which this drawer stands in for on mobile. -->
                <LocaleOptionList v-if="CAN_CHOOSE_LOCALE" class="mt-1" @chosen="mobileNavOpen = false" />
              </template>
            </BandSidebar>
          </div>
        </div>
      </Drawer>
    </template>
  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useHead } from '@unhead/vue'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useBandSpaceLiveRefresh } from '../composables/useBandSpaceLiveRefresh.js'
import { useBandSpaceNavigation } from '../composables/useBandSpaceNavigation.js'
import { CAN_CHOOSE_LOCALE } from '../composables/useLocaleChoice.js'
import { BAND_SPACE_ROUTES, SECTION_NAMES } from '../constants/bandSpace.js'
import { useBandSpaceStore } from '../store/bandSpace/bandSpace.js'
import { useNotificationStore } from '../store/notification/notification.js'
import { useUserSecurityStore } from '../store/user/security.js'
import { formatDateLong } from '../utils/date.js'
import MenuBand from '../views/Global/MenuBand.vue'
import BandSidebar from './BandSpace/BandSidebar.vue'
import BandSpaceSelector from './BandSpace/BandSpaceSelector.vue'
import CommandPalette from './BandSpace/CommandPalette.vue'
import LocaleOptionList from './Global/LocaleOptionList.vue'
import SkipLink from './SkipLink.vue'

const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()
const notificationStore = useNotificationStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()

// Mirror of AppNavbarUserCluster's menuItems for the mobile drawer's
// collapsible account section. Keep in sync if the desktop avatar menu
// changes — extracting to a composable would be cleaner once a third
// consumer appears.
const userMenuEntries = computed(() => {
  const entries = [
    {
      label: t('user_menu.profile'),
      icon: 'pi-user',
      to: () => ({
        name: 'app_user_public_profile',
        params: { username: userSecurityStore.user?.username }
      })
    },
    {
      label: t('user_menu.announces'),
      icon: 'pi-megaphone',
      to: () => ({ name: 'app_user_announces' })
    },
    {
      label: t('user_menu.publications'),
      icon: 'pi-file-edit',
      to: () => ({ name: 'app_user_publications' })
    },
    {
      label: t('user_menu.courses'),
      icon: 'pi-book',
      to: () => ({ name: 'app_user_courses' })
    },
    {
      label: t('user_menu.photos'),
      icon: 'pi-images',
      to: () => ({ name: 'app_user_galleries' })
    },
    {
      label: t('band_space.layout.account_settings'),
      icon: 'pi-cog',
      to: () => ({ name: 'app_user_settings' })
    }
  ]
  if (userSecurityStore.isAdmin) {
    entries.push({
      label: t('user_menu.admin'),
      icon: 'pi-shield',
      to: () => ({ name: 'admin_dashboard' })
    })
  }
  entries.push({
    label: t('user_menu.logout'),
    icon: 'pi-sign-out',
    action: () => {
      trackUmamiEvent('user-logout')
      userSecurityStore.logout()
    }
  })
  return entries
})

function handleUserMenuClick(entry) {
  if (entry.action) {
    entry.action()
  } else if (entry.to) {
    router.push(entry.to())
  }
  mobileNavOpen.value = false
}

const { currentSpace, setLastSpaceId, handleRedirect, validateCurrentSpace } =
  useBandSpaceNavigation()

const deletionScheduledFor = computed(() => currentSpace.value?.deletion_scheduled_datetime ?? null)

const isLoading = ref(true)
const hasError = ref(false)
const mobileNavOpen = ref(false)
const paletteVisible = ref(false)

/**
 * Bound here rather than globally on purpose: outside a band space there is nothing for the palette
 * to search, and swallowing the browser's own Ctrl+K on the rest of the site would be a regression.
 */
function handleSearchShortcut(event) {
  if (event.key?.toLowerCase() !== 'k' || !(event.ctrlKey || event.metaKey)) {
    return
  }
  // No space in the URL yet means no space to search, so leave the shortcut alone.
  if (!route.params.id) {
    return
  }

  event.preventDefault()
  paletteVisible.value = true
}

// Desktop sidebar collapse state — persists across reloads and drives the
// --band-sidebar-width CSS variable so the aside, the navbar logo zone,
// and any future consumer stay in sync via one declaration.
const SIDEBAR_COLLAPSED_KEY = 'bandSidebarCollapsed'
const SIDEBAR_WIDTH_EXPANDED = '11rem'
const SIDEBAR_WIDTH_COLLAPSED = '4rem'

const sidebarCollapsed = ref(false)
try {
  sidebarCollapsed.value = window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1'
} catch {
  // localStorage may throw in privacy modes — fall back to expanded.
}

watch(
  sidebarCollapsed,
  (collapsed) => {
    document.documentElement.style.setProperty(
      '--band-sidebar-width',
      collapsed ? SIDEBAR_WIDTH_COLLAPSED : SIDEBAR_WIDTH_EXPANDED
    )
    try {
      window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? '1' : '0')
    } catch {
      // ignore
    }
  },
  { immediate: true }
)

// Page title based on current space and route. The pipe goes in as a parameter because vue-i18n
// reads a literal one as a plural separator.
const TITLE_SEPARATOR = '|'
const pageTitle = computed(() => {
  const spaceName = currentSpace.value?.name
  const routeName = route.name
  const section = SECTION_NAMES[routeName] || t('band_space.module_name.band_space')

  if (spaceName) {
    return t('band_space.layout.page_title_with_space', {
      section,
      space: spaceName,
      separator: TITLE_SEPARATOR
    })
  }
  return t('band_space.layout.page_title', { section, separator: TITLE_SEPARATOR })
})

useHead({
  title: pageTitle
})

// A rename, a role change or a scheduled deletion of the open space reaches the header, the sidebar
// and the deletion banner, which all read the space list. Quietly: a blip keeps the list on screen.
useBandSpaceLiveRefresh({
  bandSpaceId: () => route.params.id ?? null,
  modules: ['settings'],
  refresh: () => bandSpaceStore.loadMyBandSpaces({ quiet: true })
})

onMounted(() => {
  loadSpaces()
  window.addEventListener('keydown', handleSearchShortcut)
})

onUnmounted(() => window.removeEventListener('keydown', handleSearchShortcut))

async function loadSpaces() {
  isLoading.value = true
  hasError.value = false

  try {
    await bandSpaceStore.loadMyBandSpaces()

    if (route.name === BAND_SPACE_ROUTES.INDEX) {
      handleRedirect()
    } else if (route.params.id) {
      if (!validateCurrentSpace()) {
        toast.add({
          severity: 'warn',
          summary: t('band_space.layout.not_found_summary'),
          detail: t('band_space.layout.not_found_detail'),
          life: 5000
        })
      }
    }
  } catch (error) {
    hasError.value = true
    toast.add({
      severity: 'error',
      summary: t('band_space.common.error'),
      detail: t('band_space.layout.load_spaces_error'),
      life: 5000
    })
  } finally {
    isLoading.value = false
  }
}

function retry() {
  loadSpaces()
}

// Watch for route changes
watch(
  () => route.name,
  (newName) => {
    if (isLoading.value || hasError.value) return

    if (newName === BAND_SPACE_ROUTES.INDEX) {
      handleRedirect()
    }
  }
)

// Watch for space ID changes in URL and save to localStorage
watch(
  () => route.params.id,
  (newId) => {
    if (newId) {
      setLastSpaceId(newId)
    }

    if (isLoading.value || hasError.value || !newId) return

    if (!validateCurrentSpace()) {
      toast.add({
        severity: 'warn',
        summary: t('band_space.layout.not_found_summary'),
        detail: t('band_space.layout.not_found_detail'),
        life: 5000
      })
    }
  },
  { immediate: true }
)
</script>
