<template>
    <div class="bg-surface-0 dark:bg-surface-900 shadow-sm rounded-2xl overflow-hidden h-full flex flex-col">
        <div class="p-4 flex flex-col flex-1">
            <div class="flex gap-4">
                <!-- Avatar -->
                <router-link v-if="!user.deletion_datetime" :to="profileRoute" class="flex-shrink-0">
                    <Avatar
                        v-if="user.profile_picture_url"
                        :image="user.profile_picture_url"
                        :pt="{ image: { alt: `Photo de ${userName}` } }"
                        class="!w-16 !h-16"
                        shape="circle"
                    />
                    <Avatar
                        v-else
                        :label="userName.charAt(0).toUpperCase()"
                        :style="getAvatarStyle(userName)"
                        class="!w-16 !h-16"
                        shape="circle"
                    />
                </router-link>
                <div v-else class="flex-shrink-0">
                    <Avatar
                        :label="userName.charAt(0).toUpperCase()"
                        :style="getAvatarStyle(userName)"
                        class="!w-16 !h-16"
                        shape="circle"
                    />
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <router-link v-if="!user.deletion_datetime" :to="profileRoute" class="font-medium text-surface-900 dark:text-surface-0 text-lg leading-tight hover:text-primary transition-colors block truncate">
                        {{ userName }}
                    </router-link>
                    <span v-else class="font-medium text-surface-500 text-lg leading-tight block truncate">
                        {{ userName }}
                    </span>
                    <p class="text-surface-500 dark:text-surface-400 text-sm mt-0.5 truncate">
                        <span v-if="type === 1">recherche un {{ instrument.toLowerCase() }}</span>
                        <span v-else>{{ instrument }}</span>
                    </p>
                </div>
            </div>

            <!-- Tags -->
            <div class="mt-3">
                <span
                    v-for="style in visibleStyles"
                    :key="style.name"
                    class="inline-block px-3 py-1 text-xs font-medium rounded-full mr-2 mb-2 bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300"
                >
                    {{ style.name }}
                </span>
                <span
                    v-if="hasMoreStyles(styles)"
                    v-tooltip.top="allStylesText"
                    class="inline-block px-3 py-1 text-xs font-medium rounded-full mr-2 mb-2 bg-surface-100 dark:bg-surface-800 text-surface-400 cursor-help"
                >
                    +{{ styles.length - MAX_VISIBLE_STYLES }}
                </span>
            </div>

            <!-- Location -->
            <div class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                <i class="pi pi-map-marker mr-1" />{{ location_name }}
                <span v-if="distance" class="opacity-70"> · {{ formattedDistance }}</span>
            </div>

            <!-- Buttons -->
            <div class="flex gap-2 mt-auto pt-4">
                <button
                    @click="$router.push(profileRoute)"
                    class="flex-1 py-2.5 rounded-xl bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300 text-sm font-medium hover:bg-surface-200 dark:hover:bg-surface-700 transition-all cursor-pointer"
                >
                    <i class="pi pi-user mr-2" />Profil
                </button>
                <button
                    v-if="!isOwnAnnounce"
                    @click="handleContact"
                    class="flex-1 py-2.5 rounded-xl bg-surface-900 dark:bg-surface-100 text-white dark:text-surface-900 text-sm font-medium transition-all hover:bg-surface-800 dark:hover:bg-surface-200 cursor-pointer"
                >
                    <i class="pi pi-envelope mr-2" />Contacter
                </button>
            </div>
        </div>

        <SendMessageModal v-model:visible="showMessageModal" :selected-recipient="user" />
        <AuthRequiredModal v-model:visible="showAuthModal" variant="contact" :musician-name="userName" />
    </div>
</template>

<script setup lang="ts">
import Avatar from 'primevue/avatar'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { computed, ref } from 'vue'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import { displayName } from '../../helper/user/displayName.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { hasMoreStyles, MAX_VISIBLE_STYLES } from '../../utils/styles.js'

const props = defineProps({
    type: { type: Number, required: true },
    user: { type: Object, required: true },
    instrument: { type: String, required: true },
    styles: { type: Array, required: true },
    location_name: { type: String, required: true },
    distance: { type: [Number, String], default: null },
    from: { type: String, default: null }
})

const userName = computed(() => displayName(props.user))

const visibleStyles = computed(() => props.styles.slice(0, MAX_VISIBLE_STYLES))
const allStylesText = computed(() => props.styles.map((s) => s.name).join(', '))

const userSecurityStore = useUserSecurityStore()
const showMessageModal = ref(false)
const showAuthModal = ref(false)

const isOwnAnnounce = computed(() => userSecurityStore.userProfile?.id === props.user.id)

const profileRoute = computed(() => {
    const route = {
        name: props.user.has_musician_profile ? 'app_user_musician_profile' : 'app_user_public_profile',
        params: { username: props.user.username }
    }
    // Add from query param for contextual back navigation
    if (props.from) {
        route.query = { from: props.from }
    }
    return route
})

const formattedDistance = computed(() => {
    if (!props.distance) return ''
    const dist = Number(props.distance)
    return dist < 10 ? `${dist.toFixed(1)} km` : `${Math.round(dist)} km`
})

function handleContact() {
    trackUmamiEvent('musician-result-contact')
    if (!userSecurityStore.isAuthenticated) {
        showAuthModal.value = true
        return
    }
    showMessageModal.value = true
}
</script>
