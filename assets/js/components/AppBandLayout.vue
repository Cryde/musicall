<template>
    <div class="relative min-h-[50rem] bg-surface-50 dark:bg-surface-950">
        <template v-if="isLoading">
            <div class="flex items-center justify-center min-h-[50rem]">
                <i class="pi pi-spin pi-spinner text-4xl"></i>
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
import { useBandSpaceStore } from '../store/bandSpace/bandSpace.js'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const LAST_BAND_SPACE_KEY = 'lastBandSpaceId'

const bandSpaceStore = useBandSpaceStore()
const route = useRoute()
const router = useRouter()

const isLoading = ref(true)

onMounted(async () => {
    await bandSpaceStore.loadMyBandSpaces()
    isLoading.value = false

    // If we're at /band (no id), redirect appropriately
    if (route.name === 'app_band_index') {
        handleRedirect()
    }
})

// Watch for route changes to /band (index)
watch(() => route.name, (newName) => {
    if (newName === 'app_band_index' && !isLoading.value) {
        handleRedirect()
    }
})

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
