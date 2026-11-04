<template>
  <nav class="sticky top-0 z-30 flex items-stretch bg-surface-0 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-800 h-16">
    <!-- Logo zone: fills the sidebar column on lg+ so it aligns with the sidebar below. Below lg it
         starts on the page's own edge, like the site header. -->
    <RouterLink
      :to="{ name: 'app_home' }"
      class="flex items-center px-4 max-md:pl-6 max-sm:pr-2 md:max-lg:pl-12 lg:w-[var(--band-sidebar-width)] lg:shrink-0 lg:justify-center"
      :aria-label="$t('common.musicall_home')"
    >
      <span class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2">
        <img src="../../../image/logo-2.webp" :alt="$t('common.musicall_logo')" width="106" height="16" class="h-4 w-auto" />
      </span>
    </RouterLink>

    <div class="flex flex-1 items-center gap-3 max-sm:gap-1 px-4 max-lg:pl-0 max-md:pr-6 md:max-lg:pr-12 lg:pr-20 min-w-0">
      <div class="hidden lg:block shrink-0">
        <BandSpaceSelector />
      </div>

      <!-- A shortcut nobody knows about helps nobody, hence the visible twin of Ctrl+K. On a phone it is
           one more round icon beside the others, the only way four of them fit next to the logo. -->
      <button
        v-if="currentSpaceId"
        type="button"
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700 text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors duration-150 shrink-0 max-sm:ml-auto max-sm:size-10 max-sm:justify-center max-sm:rounded-full max-sm:border-0 max-sm:p-0"
        :aria-label="$t('band_menu.search_aria_label')"
        aria-keyshortcuts="Control+K Meta+K"
        @click="emit('open-search')"
      >
        <i class="pi pi-search text-sm max-sm:text-xl shrink-0" aria-hidden="true" />
        <span class="hidden sm:inline text-sm">{{ $t('band_menu.search') }}</span>
        <kbd
          class="hidden lg:inline font-sans text-[0.65rem] px-1.5 py-0.5 rounded border border-surface-300 dark:border-surface-600"
          aria-hidden="true"
        >
          {{ SHORTCUT_LABEL }}
        </kbd>
      </button>

      <!-- Below lg, as in the site header: a member's inboxes with their counts, then the menu. The
           negative margin lines the last icon up with the page's edge rather than its button's. -->
      <div v-if="isCompactNav" class="sm:ml-auto flex items-center gap-1 -mr-2.5">
        <NavbarInbox v-if="userSecurityStore.isAuthenticated" />
        <button
          v-if="currentSpaceId"
          ref="menuButton"
          type="button"
          class="flex items-center justify-center w-10 h-10 rounded-full cursor-pointer text-surface-900 dark:text-surface-100 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
          :aria-label="$t('common.open_navigation_menu')"
          aria-haspopup="dialog"
          :aria-expanded="isMenuOpen"
          :aria-controls="isMenuOpen ? MENU_ID : undefined"
          @click="isMenuOpen = true"
        >
          <i class="pi pi-bars text-xl" aria-hidden="true" />
        </button>
      </div>

      <template v-else>
        <div class="flex-1"></div>

        <RouterLink :to="{ name: 'app_home' }" custom v-slot="{ href, navigate }">
          <a
            :href="href"
            @click="(e) => { if (!bandSpaceStore.isCreating) navigate(e) }"
            :class="[
              'flex items-center text-xs gap-2 p-2 rounded-lg transition-colors duration-150 border border-transparent shrink-0',
              bandSpaceStore.isCreating
                ? 'cursor-not-allowed opacity-50'
                : 'cursor-pointer hover:underline'
            ]"
          >
            <span class="font-medium">{{ $t('band_menu.back_to_musicall') }}</span>
          </a>
        </RouterLink>

        <div class="flex">
          <AppNavbarUserCluster />
        </div>
      </template>
    </div>
  </nav>

  <BandSpaceMobileMenu
    v-if="isCompactNav && currentSpaceId"
    :id="MENU_ID"
    v-model:visible="isMenuOpen"
    @after-hide="returnFocus"
  />
  <CreateBandSpaceModal @created="handleBandSpaceCreated" />
</template>

<script setup>
import { useTemplateRef } from 'vue'
import AppNavbarUserCluster from '../../components/AppNavbarUserCluster.vue'
import BandSpaceMobileMenu from '../../components/BandSpace/BandSpaceMobileMenu.vue'
import BandSpaceSelector from '../../components/BandSpace/BandSpaceSelector.vue'
import CreateBandSpaceModal from '../../components/BandSpace/CreateBandSpaceModal.vue'
import NavbarInbox from '../../components/Global/NavbarInbox.vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { useCompactMenu } from '../../composables/useCompactMenu.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'
import { useUserSecurityStore } from '../../store/user/security.js'

const MENU_ID = 'band-space-menu'

// Showing "Ctrl K" to somebody on a Mac would be advertising a shortcut that does not exist there.
const SHORTCUT_LABEL = /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘ K' : 'Ctrl K'

const emit = defineEmits(['open-search'])

const bandSpaceStore = useBandSpaceStore()
const userSecurityStore = useUserSecurityStore()
const { currentSpaceId, navigateToSpace } = useBandSpaceNavigation()

const menuButton = useTemplateRef('menuButton')
const { isCompactNav, isMenuOpen, returnFocus } = useCompactMenu(menuButton)

function handleBandSpaceCreated(newSpace) {
  navigateToSpace(newSpace.id, BAND_SPACE_ROUTES.DASHBOARD)
}
</script>
