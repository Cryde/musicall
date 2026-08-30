<template>
  <nav class="sticky top-0 z-30 flex items-stretch bg-surface-0 dark:bg-surface-900 border-b border-surface-200 dark:border-surface-800 h-16">
    <button
      v-if="currentSpaceId"
      type="button"
      class="lg:hidden text-surface-700 dark:text-surface-200 bg-transparent border-0 px-3 py-2 ml-2 my-2 rounded-lg hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
      aria-label="Ouvrir le menu de navigation"
      @click="mobileNavOpen = !mobileNavOpen"
    >
      <i class="pi pi-bars text-xl" aria-hidden="true" />
    </button>

    <!-- Logo zone — fills the sidebar column on lg+ so it aligns with the
         sidebar below; sits flush after the hamburger on mobile. -->
    <RouterLink
      :to="{ name: 'app_home' }"
      class="flex items-center px-4 lg:w-[var(--band-sidebar-width)] lg:shrink-0 lg:justify-center"
      aria-label="Accueil MusicAll"
    >
      <span class="bg-[#5b87ae] dark:bg-transparent rounded-xs px-4 py-2">
        <img src="../../../image/logo-2.webp" alt="Logo MusicAll" width="106" height="16" class="h-4 w-auto" />
      </span>
    </RouterLink>

    <div class="flex flex-1 items-center gap-3 px-4 lg:pr-20 min-w-0">
      <div class="hidden lg:block shrink-0">
        <BandSpaceSelector />
      </div>

      <!-- A shortcut nobody knows about helps nobody, hence the visible twin of Ctrl+K. -->
      <button
        v-if="currentSpaceId"
        type="button"
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg border border-surface-200 dark:border-surface-700 text-surface-600 dark:text-surface-300 hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors duration-150 shrink-0"
        aria-label="Rechercher dans le Band Space"
        aria-keyshortcuts="Control+K Meta+K"
        @click="emit('open-search')"
      >
        <i class="pi pi-search text-sm shrink-0" aria-hidden="true" />
        <span class="hidden sm:inline text-sm">Rechercher</span>
        <kbd
          class="hidden lg:inline font-sans text-[0.65rem] px-1.5 py-0.5 rounded border border-surface-300 dark:border-surface-600"
          aria-hidden="true"
        >
          {{ SHORTCUT_LABEL }}
        </kbd>
      </button>

      <div class="flex-1"></div>

      <RouterLink :to="{ name: 'app_home' }" custom v-slot="{ href, navigate }">
        <a
          :href="href"
          @click="(e) => { if (!bandSpaceStore.isCreating) navigate(e) }"
          :class="[
            'hidden lg:flex items-center text-xs gap-2 p-2 rounded-lg transition-colors duration-150 border border-transparent shrink-0',
            bandSpaceStore.isCreating
              ? 'cursor-not-allowed opacity-50'
              : 'cursor-pointer hover:underline'
          ]"
        >
          <span class="font-medium">back to musicall</span>
        </a>
      </RouterLink>

      <div class="hidden lg:flex">
        <AppNavbarUserCluster />
      </div>
    </div>
  </nav>

  <CreateBandSpaceModal @created="handleBandSpaceCreated" />
</template>

<script setup>
import AppNavbarUserCluster from '../../components/AppNavbarUserCluster.vue'
import BandSpaceSelector from '../../components/BandSpace/BandSpaceSelector.vue'
import CreateBandSpaceModal from '../../components/BandSpace/CreateBandSpaceModal.vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

// Showing "Ctrl K" to somebody on a Mac would be advertising a shortcut that does not exist there.
const SHORTCUT_LABEL = /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘ K' : 'Ctrl K'

const mobileNavOpen = defineModel('mobileNavOpen', { type: Boolean, default: false })
const emit = defineEmits(['open-search'])

const bandSpaceStore = useBandSpaceStore()
const { currentSpaceId, navigateToSpace } = useBandSpaceNavigation()

function handleBandSpaceCreated(newSpace) {
  navigateToSpace(newSpace.id, BAND_SPACE_ROUTES.DASHBOARD)
}
</script>
