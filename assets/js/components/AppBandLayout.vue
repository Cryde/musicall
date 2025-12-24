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
                    <router-view/>
                </div>
            </div>
            <Footer/>
        </template>
    </div>
</template>
<script setup>
import Footer from '../views/Global/Footer.vue'
import MenuBand from '../views/Global/MenuBand.vue'
import Toast from 'primevue/toast'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { useHead } from '@unhead/vue'
import { useBandSpaceStore } from '../store/bandSpace/bandSpace.js'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const LAST_BAND_SPACE_KEY = 'lastBandSpaceId'

const bandSpaceStore = useBandSpaceStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const isLoading = ref(true)
const hasError = ref(false)

// Page title based on current space and route
const currentSpace = computed(() => {
  const spaceId = route.params.id
  return spaceId ? bandSpaceStore.getById(spaceId) : null
})

const pageTitle = computed(() => {
  const spaceName = currentSpace.value?.name
  const routeName = route.name

  const sectionNames = {
    'app_band_dashboard': 'Dashboard',
    'app_band_agenda': 'Agenda',
    'app_band_notes': 'Notes',
    'app_band_social': 'Social',
    'app_band_files': 'Fichiers',
    'app_band_parameters': 'Paramètres',
    'app_band_index': 'Band Space'
  }

  const section = sectionNames[routeName] || 'Band Space'

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

        // If we're at /band (no id), redirect appropriately
        if (route.name === 'app_band_index') {
            handleRedirect()
        } else if (route.params.id) {
            // Validate that the space ID in URL exists
            validateCurrentSpace()
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
watch(() => route.name, (newName) => {
    if (isLoading.value || hasError.value) return

    if (newName === 'app_band_index') {
        handleRedirect()
    }
})

// Watch for space ID changes in URL
watch(() => route.params.id, (newId) => {
    if (isLoading.value || hasError.value || !newId) return
    validateCurrentSpace()
})

function validateCurrentSpace() {
    const spaceId = route.params.id
    if (!spaceId) return

    const space = bandSpaceStore.getById(spaceId)
    if (!space) {
        toast.add({
            severity: 'warn',
            summary: 'Band Space introuvable',
            detail: 'Ce Band Space n\'existe pas ou vous n\'y avez pas accès',
            life: 5000
        })
        // Redirect to /band which will handle finding a valid space
        router.replace({ name: 'app_band_index' })
    }
}

function handleRedirect() {
    if (bandSpaceStore.spaces.length === 0) {
        // No spaces, open create modal
        bandSpaceStore.openCreateModal()
        return
    }

    // Try to get last used space from localStorage
    const lastSpaceId = localStorage.getItem(LAST_BAND_SPACE_KEY)
    const lastSpace = lastSpaceId
        ? bandSpaceStore.spaces.find(s => s.id === lastSpaceId)
        : null

    // Redirect to last used space or first space
    const targetSpace = lastSpace || bandSpaceStore.spaces[0]
    router.replace({ name: 'app_band_dashboard', params: { id: targetSpace.id } })
}
</script>
