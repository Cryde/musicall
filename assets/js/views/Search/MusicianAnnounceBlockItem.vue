<template>
    <div class="bg-surface-0 dark:bg-surface-900 shadow-sm rounded-2xl p-4 flex flex-col gap-5">
        <div class="flex-1 flex flex-col items-center gap-4">
            <router-link
                :to="profileRoute"
                class="cursor-pointer hover:opacity-80 transition-opacity"
            >
                <Avatar
                    v-if="user.profile_picture_url"
                    :image="user.profile_picture_url"
                    size="xlarge"
                    shape="circle"
                />
                <Avatar
                    v-else
                    :label="user.username.charAt(0).toUpperCase()"
                    :style="getAvatarStyle(user.username)"
                    size="xlarge"
                    shape="circle"
                />
            </router-link>

            <div class="flex flex-col items-center gap-4 w-full">
                <div class="flex flex-col items-center gap-2 w-full text-center">
                    <router-link
                        :to="profileRoute"
                        class="font-medium text-surface-900 dark:text-surface-0 text-xl leading-tight hover:text-primary transition-colors"
                    >
                        {{ user.username }}
                    </router-link>
                    <span class="text-surface-600 dark:text-surface-200 font-normal leading-tight" v-if="type === 1">
                        recherche un {{ instrument.toLowerCase() }}
                    </span>
                    <span class="text-surface-600 dark:text-surface-200 font-normal leading-tight" v-else>
                        {{ instrument }}
                    </span>
                </div>
            </div>
        </div>
        <div class="block text-center">
            <Tag
                v-for="style in visibleStyles"
                :key="style.name"
                size="small"
                :value="style.name"
                class="text-nowrap mr-2 mb-2"
                severity="info"
            />
            <Tag
                v-if="hasMoreStyles(styles)"
                v-tooltip.top="allStylesText"
                size="small"
                :value="`+${styles.length - MAX_VISIBLE_STYLES}`"
                class="text-nowrap mr-2 mb-2 cursor-help"
                severity="secondary"
            />
        </div>
        <div class="block text-center">
            {{ location_name }}
            <span v-if="distance" class="text-surface-500 dark:text-surface-400">
                ({{ formattedDistance }})
            </span>
        </div>
        <div class="flex gap-2">
            <Button
              type="button"
              severity="secondary"
              outlined
              class="flex-1"
              icon="pi pi-user"
              @click="$router.push(profileRoute)"
              v-tooltip.top="profileTooltip"
            />
            <Button
              v-if="!isOwnAnnounce"
              type="button"
              severity="secondary"
              outlined
              label="Contacter"
              class="flex-1"
              icon="pi pi-envelope"
              @click="handleContact"
            />
        </div>

        <SendMessageModal
          v-model:visible="showMessageModal"
          :selected-recipient="user"
        />
        <AuthRequiredModal
          v-model:visible="showAuthModal"
          message="Vous devez vous connecter pour envoyer un message a cet utilisateur."
        />
    </div>
</template>
<script setup lang="ts">
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { computed, ref } from 'vue'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { hasMoreStyles, MAX_VISIBLE_STYLES } from '../../utils/styles.js'

const props = defineProps(['type', 'user', 'instrument', 'styles', 'location_name', 'distance'])

const visibleStyles = computed(() => props.styles.slice(0, MAX_VISIBLE_STYLES))
const allStylesText = computed(() => props.styles.map((s) => s.name).join(', '))

const userSecurityStore = useUserSecurityStore()
const showMessageModal = ref(false)
const showAuthModal = ref(false)

const isOwnAnnounce = computed(() => {
  return userSecurityStore.userProfile?.id === props.user.id
})

const profileRoute = computed(() => ({
  name: props.type === 2 ? 'app_user_musician_profile' : 'app_user_public_profile',
  params: { username: props.user.username }
}))

const profileTooltip = computed(() =>
  props.type === 2 ? 'Voir le profil musicien' : 'Voir le profil'
)

const formattedDistance = computed(() => {
  if (!props.distance) return ''
  const dist = Number(props.distance)
  if (dist < 10) {
    return `${dist.toFixed(1)} km`
  }
  return `${Math.round(dist)} km`
})

function handleContact() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  showMessageModal.value = true
}
</script>
