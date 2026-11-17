<template>
    <div class="relative min-h-200 bg-surface-50 dark:bg-surface-950">
        <SkipLink />
        <Toast />
        <!-- Below lg the header stays on screen: the menu and a member's unread counts are always one tap away. -->
        <header class="max-lg:sticky max-lg:top-0 max-lg:z-30">
            <Menu />
        </header>
        <main id="main-content" tabindex="-1" class="bg-surface-200 dark:bg-surface-950 px-6 py-8 md:px-12 lg:px-20">
            <div class="flex flex-col gap-8">
                <router-view v-slot="{ Component }">
                    <KeepAlive :include="keepAliveIncludes" :max="3">
                        <component :is="Component" />
                    </KeepAlive>
                </router-view>
            </div>
        </main>
        <Footer/>
        <!-- Last: it appears once the auth check answers and sits fixed over the page anyway. -->
        <LocaleSuggestionBanner v-if="CAN_CHOOSE_LOCALE" />
    </div>
</template>
<script setup>
import Toast from 'primevue/toast'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { CAN_CHOOSE_LOCALE } from '../composables/useLocaleChoice.js'
import Footer from '../views/Global/Footer.vue'
import Menu from '../views/Global/Menu.vue'
import LocaleSuggestionBanner from './Global/LocaleSuggestionBanner.vue'
import SkipLink from './SkipLink.vue'

const route = useRoute()

// Each search page, kept on its routes and on the page its results open, so coming back restores it.
const KEPT_ALIVE_ON = {
  MusicianSearch: [
    'app_search_musician',
    'app_search_guitarist',
    'app_search_drummer',
    'app_search_bassist',
    'app_search_singer',
    'app_search_pianist',
    'app_user_public_profile'
  ],
  TeacherSearch: ['app_search_teacher', 'app_user_teacher_profile']
}

const keepAliveIncludes = computed(() =>
  Object.keys(KEPT_ALIVE_ON).filter((name) => KEPT_ALIVE_ON[name].includes(route.name))
)
</script>
