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
          Une erreur est survenue lors du chargement.
        </p>
        <Button label="Réessayer" icon="pi pi-refresh" @click="retry" />
      </div>
    </template>
    <template v-else>
      <MenuBand v-model:mobile-nav-open="mobileNavOpen" />
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
        <main class="flex-1 min-w-0 bg-surface-200 dark:bg-surface-950">
          <div class="px-6 py-8 md:px-12 lg:pl-8 lg:pr-20 flex flex-col gap-8">
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
          <span class="font-semibold">Navigation</span>
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
                <span class="font-medium">back to musicall</span>
              </a>
            </RouterLink>
          </div>
          <div class="flex-1 min-h-0">
            <BandSidebar
              :disabled="bandSpaceStore.isCreating"
              @navigate="mobileNavOpen = false"
            />
          </div>
          <div class="shrink-0">
            <AppNavbarUserCluster @navigate="mobileNavOpen = false" />
          </div>
        </div>
      </Drawer>
    </template>
  </div>
</template>

<script setup>
import { useHead } from '@unhead/vue'
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useBandSpaceNavigation } from '../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES, SECTION_NAMES } from '../constants/bandSpace.js'
import { useBandSpaceStore } from '../store/bandSpace/bandSpace.js'
import MenuBand from '../views/Global/MenuBand.vue'
import AppNavbarUserCluster from './AppNavbarUserCluster.vue'
import BandSidebar from './BandSpace/BandSidebar.vue'
import BandSpaceSelector from './BandSpace/BandSpaceSelector.vue'

const bandSpaceStore = useBandSpaceStore()
const route = useRoute()
const toast = useToast()

const { currentSpace, setLastSpaceId, handleRedirect, validateCurrentSpace } =
  useBandSpaceNavigation()

const isLoading = ref(true)
const hasError = ref(false)
const mobileNavOpen = ref(false)

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

// Page title based on current space and route
const pageTitle = computed(() => {
  const spaceName = currentSpace.value?.name
  const routeName = route.name
  const section = SECTION_NAMES[routeName] || 'Band Space'

  if (spaceName) {
    return `${section} - ${spaceName} | MusicAll`
  }
  return `${section} | MusicAll`
})

useHead({
  title: pageTitle
})

onMounted(() => {
  loadSpaces()
})

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
          summary: 'Band Space introuvable',
          detail: "Ce Band Space n'existe pas ou vous n'y avez pas accès",
          life: 5000
        })
      }
    }
  } catch (error) {
    hasError.value = true
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de charger vos Band Spaces',
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
        summary: 'Band Space introuvable',
        detail: "Ce Band Space n'existe pas ou vous n'y avez pas accès",
        life: 5000
      })
    }
  },
  { immediate: true }
)
</script>
