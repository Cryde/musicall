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
        <p class="text-surface-600 dark:text-surface-400">Une erreur est survenue lors du chargement.</p>
        <Button label="Réessayer" icon="pi pi-refresh" @click="retry" />
      </div>
    </template>
    <template v-else>
      <MenuBand />
      <div class="bg-surface-200 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
        <div class="flex flex-col gap-8">
          <router-view />
        </div>
      </div>
      <Footer />
    </template>
  </div>
</template>

<script setup>
import { useHead } from '@unhead/vue'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useBandSpaceNavigation } from '../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES, SECTION_NAMES } from '../constants/bandSpace.js'
import { useBandSpaceStore } from '../store/bandSpace/bandSpace.js'
import Footer from '../views/Global/Footer.vue'
import MenuBand from '../views/Global/MenuBand.vue'

const bandSpaceStore = useBandSpaceStore()
const route = useRoute()
const toast = useToast()

const { currentSpace, setLastSpaceId, handleRedirect, validateCurrentSpace } =
  useBandSpaceNavigation()

const isLoading = ref(true)
const hasError = ref(false)

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
