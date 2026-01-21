<template>
    <div class="relative min-h-200 bg-surface-50 dark:bg-surface-950">
        <Toast />
        <Menu />
        <div class="bg-surface-200 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col gap-8">
                <router-view v-slot="{ Component }">
                    <KeepAlive :include="keepAliveIncludes" :max="3">
                        <component :is="Component" />
                    </KeepAlive>
                </router-view>
            </div>
        </div>
        <Footer/>
    </div>
</template>
<script setup>
import Toast from 'primevue/toast'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Footer from '../views/Global/Footer.vue'
import Menu from '../views/Global/Menu.vue'

const route = useRoute()

// Context zones: routes where specific components should remain cached
const musicianSearchContext = [
    'app_search_musician',
    'app_search_guitarist',
    'app_search_drummer',
    'app_search_bassist',
    'app_search_singer',
    'app_search_pianist',
    'app_user_public_profile',
    'app_user_musician_profile'
]

// Dynamic include list based on current route context
const keepAliveIncludes = computed(() => {
    if (musicianSearchContext.includes(route.name)) {
        return ['MusicianSearch']
    }
    return []
})
</script>
