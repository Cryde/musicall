<template>
  <div class="py-6 md:py-10">
    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <ProgressSpinner />
    </div>

    <!-- Not found state -->
    <div v-else-if="notFound" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
      <i class="pi pi-user-minus text-4xl text-surface-400 mb-4" />
      <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
        Profil musicien non trouvé
      </h2>
      <p class="text-surface-500 dark:text-surface-400 mb-4">
        Cet utilisateur n'a pas encore créé son profil musicien.
      </p>
      <Button
        label="Voir le profil"
        icon="pi pi-user"
        severity="info"
        @click="$router.push({ name: 'app_user_public_profile', params: { username: route.params.username } })"
      />
    </div>

    <!-- Profile content -->
    <div v-else-if="profile" class="flex flex-col gap-6">
      <!-- Header with back button and profile picture -->
      <div class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <div class="flex items-center gap-4">
          <!-- Back button: mobile only -->
          <div class="md:hidden">
            <Button
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              rounded
              aria-label="Retour"
              @click="handleBack"
            />
          </div>
          <Avatar
            v-if="profile.profile_picture_url"
            :image="profile.profile_picture_url"
            :pt="{ image: { alt: `Photo de ${profile.username}` } }"
            size="large"
            shape="circle"
            role="img"
            class="md:!w-16 md:!h-16"
            :aria-label="`Photo de ${profile.username}`"
          />
          <Avatar
            v-else
            :label="profile.username.charAt(0).toUpperCase()"
            :style="getAvatarStyle(profile.username)"
            size="large"
            shape="circle"
            role="img"
            class="md:!w-16 md:!h-16"
            :aria-label="`Avatar de ${profile.username}`"
          />
          <div class="flex-1 min-w-0">
            <h1 class="hidden md:block text-2xl font-bold text-surface-900 dark:text-surface-0">
              Profil musicien
            </h1>
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-surface-500 dark:text-surface-400 truncate">
                @{{ profile.username }}
              </span>
              <router-link
                :to="{ name: 'app_user_public_profile', params: { username: profile.username } }"
                class="hidden md:inline text-sm text-primary hover:underline"
              >
                Voir le profil
              </router-link>
            </div>
          </div>
          <div class="flex gap-2">
            <Button
              v-if="isOwnProfile"
              icon="pi pi-pencil"
              severity="secondary"
              rounded
              v-tooltip.bottom="'Modifier'"
              aria-label="Modifier le profil musicien"
              @click="showEditModal = true"
            />
            <div v-if="!isOwnProfile" class="hidden md:block">
              <ShareButton
                :url="shareUrl"
                :title="shareTitle"
              />
            </div>
            <div v-if="!isOwnProfile" class="md:hidden">
              <Button
                icon="pi pi-envelope"
                rounded
                aria-label="Contacter"
                @click="handleContact"
              />
            </div>
            <div v-if="!isOwnProfile" class="hidden md:block">
              <Button
                label="Contacter"
                icon="pi pi-envelope"
                rounded
                @click="handleContact"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Two columns on desktop -->
      <div class="flex flex-col lg:flex-row gap-6">
        <!-- Left Sidebar -->
        <div class="lg:w-80 shrink-0 flex flex-col gap-6">
          <!-- Availability status -->
          <div v-if="profile.availability_status" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              Disponibilité
            </h2>
            <Tag
              :value="profile.availability_status_label"
              :severity="getAvailabilitySeverity(profile.availability_status)"
            />
          </div>

          <!-- Instruments -->
          <div v-if="profile.instruments?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              Instruments
            </h2>
            <div class="flex flex-col gap-2">
              <div
                v-for="instrument in profile.instruments"
                :key="instrument.instrument_id"
                class="flex items-center justify-between p-2 rounded-lg bg-surface-50 dark:bg-surface-800"
              >
                <span class="font-medium text-surface-900 dark:text-surface-0 text-sm">
                  {{ instrument.instrument_name }}
                </span>
                <Tag
                  :value="instrument.skill_level_label"
                  :severity="getSkillLevelSeverity(instrument.skill_level)"
                  size="small"
                />
              </div>
            </div>
          </div>

          <!-- Styles -->
          <div v-if="profile.styles?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
            <h2 class="text-base font-semibold text-surface-900 dark:text-surface-0 mb-3">
              Styles musicaux
            </h2>
            <div class="flex flex-wrap gap-2">
              <Tag
                v-for="style in profile.styles"
                :key="style.id"
                :value="style.name"
                severity="info"
              />
            </div>
          </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col gap-6">
          <!-- Media Showcase -->
          <MediaShowcase
            :is-own-profile="isOwnProfile"
            :media-items="isOwnProfile ? null : profile.media"
          />

          <!-- Musician Announces -->
          <div v-if="profile.musician_announces?.length || isOwnProfile" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">
                Annonces
              </h2>
              <Button
                v-if="isOwnProfile"
                icon="pi pi-plus"
                label="Ajouter"
                size="small"
                @click="$router.push({ name: 'app_user_announces' })"
              />
            </div>

            <!-- Announces list -->
            <div v-if="profile.musician_announces?.length" class="flex flex-col gap-4">
              <MusicianAnnounceItem
                v-for="announce in profile.musician_announces"
                :key="announce.id"
                :announce="announce"
              />
            </div>

            <!-- Empty state for owner -->
            <div v-else-if="isOwnProfile" class="flex flex-col items-center justify-center py-8 text-center">
              <div class="flex items-center justify-center w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900/30 mb-4">
                <i class="pi pi-megaphone text-2xl text-primary-600 dark:text-primary-400" />
              </div>
              <p class="text-surface-500 dark:text-surface-400 mb-4">
                Vous n'avez pas encore d'annonce
              </p>
              <Button
                label="Créer une annonce"
                icon="pi pi-plus"
                @click="$router.push({ name: 'app_user_announces' })"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit modal -->
    <EditMusicianProfileModal
      v-model:visible="showEditModal"
      :musician-profile="profile"
      @saved="handleProfileSaved"
    />

    <!-- Contact modals -->
    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="contactRecipient"
    />
    <AuthRequiredModal
      v-model:visible="showAuthModal"
      message="Vous devez vous connecter pour envoyer un message à cet utilisateur."
    />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import ShareButton from '../../../components/ShareButton.vue'
import EditMusicianProfileModal from '../../../components/User/Profile/EditMusicianProfileModal.vue'
import MediaShowcase from '../../../components/User/Profile/MediaShowcase.vue'
import MusicianAnnounceItem from '../../../components/User/Profile/MusicianAnnounceItem.vue'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { getAvatarStyle } from '../../../utils/avatar.js'

const route = useRoute()
const router = useRouter()
const musicianProfileStore = useMusicianProfileStore()
const userSecurityStore = useUserSecurityStore()

// Contextual back navigation based on where user came from
function handleBack() {
  const from = route.query.from
  switch (from) {
    case 'search':
    case 'annonces':
      // Use router.back() to preserve KeepAlive state and scroll position
      router.back()
      break
    case 'home':
      router.push({ name: 'app_home' })
      break
    case 'profile':
      router.push({ name: 'app_user_public_profile', params: { username: route.params.username } })
      break
    default:
      // Fallback: try back, or go to public profile
      if (window.history.length > 1) {
        router.back()
      } else {
        router.push({ name: 'app_user_public_profile', params: { username: route.params.username } })
      }
  }
}

const isLoading = ref(true)
const notFound = ref(false)
const showEditModal = ref(false)
const showMessageModal = ref(false)
const showAuthModal = ref(false)
const contactRecipient = ref(null)

const profile = computed(() => musicianProfileStore.profile)

const isOwnProfile = computed(() => {
  if (!profile.value || !userSecurityStore.userProfile) return false
  return userSecurityStore.userProfile.id === profile.value.user_id
})

const pageTitle = computed(() => {
  if (profile.value) {
    return `Profil musicien - ${profile.value.username} - MusicAll`
  }
  return 'Profil musicien - MusicAll'
})

const shareUrl = computed(() => {
  return window.location.href
})

const shareTitle = computed(() => {
  if (profile.value) {
    return `Découvrez le profil musicien de ${profile.value.username} sur MusicAll`
  }
  return 'Profil musicien sur MusicAll'
})

useTitle(pageTitle)

function getAvailabilitySeverity(status) {
  switch (status) {
    case 'looking_for_band':
      return 'success'
    case 'available_for_sessions':
      return 'info'
    case 'open_to_collaborations':
      return 'warn'
    case 'not_available':
      return 'secondary'
    default:
      return 'info'
  }
}

function getSkillLevelSeverity(level) {
  switch (level) {
    case 'beginner':
      return 'secondary'
    case 'intermediate':
      return 'info'
    case 'advanced':
      return 'warn'
    case 'professional':
      return 'success'
    default:
      return 'info'
  }
}

async function loadProfile() {
  isLoading.value = true
  notFound.value = false

  try {
    await musicianProfileStore.loadPublicProfile(route.params.username)
  } catch (error) {
    if (error.response?.status === 404) {
      notFound.value = true
    } else {
      notFound.value = true
    }
  } finally {
    isLoading.value = false
  }
}

function handleProfileSaved() {
  loadProfile()
}

function handleContact() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  contactRecipient.value = {
    id: profile.value.user_id,
    username: profile.value.username
  }
  showMessageModal.value = true
}

watch(() => route.params.username, (newUsername) => {
  if (newUsername) {
    loadProfile()
  }
})

onMounted(() => {
  loadProfile()
})

onUnmounted(() => {
  musicianProfileStore.clear()
})
</script>
