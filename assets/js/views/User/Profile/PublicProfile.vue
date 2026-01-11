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
        Profil non trouvé
      </h2>
      <p class="text-surface-500 dark:text-surface-400 mb-4">
        Ce profil n'existe pas ou n'est pas accessible.
      </p>
      <Button
        label="Retour à l'accueil"
        icon="pi pi-home"
        severity="info"
        @click="$router.push({ name: 'app_home' })"
      />
    </div>

    <!-- Private profile state -->
    <div v-else-if="isPrivate" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-8 text-center">
      <i class="pi pi-lock text-4xl text-surface-400 mb-4" />
      <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-2">
        Ce profil est privé
      </h2>
      <p class="text-surface-500 dark:text-surface-400 mb-4">
        L'utilisateur a choisi de garder son profil privé.
      </p>
      <Button
        label="Retour à l'accueil"
        icon="pi pi-home"
        severity="info"
        @click="$router.push({ name: 'app_home' })"
      />
    </div>

    <!-- Profile content -->
    <div v-else-if="profile" class="flex flex-col gap-6">
      <!-- Profile header -->
      <section class="bg-surface-0 dark:bg-surface-900 rounded-xl pb-10">
        <!-- Banner with avatar -->
        <div class="p-4 md:p-6 relative">
          <!-- Cover picture -->
          <div
            class="h-[200px] md:h-[270px] bg-cover bg-center rounded-2xl relative"
            :class="profile.cover_picture_url ? '' : 'bg-gradient-to-r from-primary-500 to-primary-700'"
            :style="profile.cover_picture_url ? { backgroundImage: `url(${profile.cover_picture_url})` } : {}"
          >
            <!-- Cover picture edit button -->
            <button
              v-if="isOwnProfile"
              class="absolute top-4 right-4 bg-black/50 hover:bg-black/70 text-white rounded-full p-2.5 transition-colors"
              title="Modifier la photo de couverture"
              @click="coverPictureInputRef?.click()"
            >
              <i class="pi pi-camera" />
            </button>
          </div>
          <!-- Avatar centered at bottom -->
          <div class="absolute left-1/2 transform -translate-x-1/2 bottom-[-50px]">
            <div class="relative">
              <img
                v-if="profile.profile_picture_url"
                :src="profile.profile_picture_url"
                :alt="profile.username"
                class="w-[120px] h-[120px] md:w-[140px] md:h-[140px] rounded-full border-[6px] border-surface-0 dark:border-surface-900 object-cover cursor-pointer hover:opacity-90 transition-opacity"
                @click="showProfilePictureViewModal = true"
              />
              <div
                v-else
                class="w-[120px] h-[120px] md:w-[140px] md:h-[140px] rounded-full border-[6px] border-surface-0 dark:border-surface-900 flex items-center justify-center text-4xl md:text-5xl font-bold"
                :style="getAvatarStyle(profile.username)"
              >
                {{ profile.username.charAt(0).toUpperCase() }}
              </div>
              <!-- Profile picture edit button -->
              <button
                v-if="isOwnProfile"
                class="absolute bottom-1 right-1 bg-primary-500 hover:bg-primary-600 text-white rounded-full p-2 transition-colors"
                title="Modifier la photo de profil"
                @click="profilePictureInputRef?.click()"
              >
                <i class="pi pi-camera text-sm" />
              </button>
            </div>
          </div>
        </div>

        <!-- Profile info centered -->
        <div class="px-6 md:px-12 flex flex-col items-center gap-5 mt-[60px]">
          <!-- Username -->
          <div class="flex flex-col items-center gap-1.5 w-full">
            <h1 class="m-0 font-bold text-surface-900 dark:text-surface-0 text-2xl md:text-3xl text-center">
              {{ profile.username }}
            </h1>
            <span class="text-surface-500 dark:text-surface-400 text-base">
              @{{ profile.username }}
            </span>
            <!-- Location -->
            <div v-if="profile.location" class="flex items-center justify-center gap-1 text-surface-500 dark:text-surface-400 text-sm">
              <i class="pi pi-map-marker text-xs" />
              {{ profile.location }}
            </div>
          </div>

          <!-- Bio -->
          <p
            v-if="profile.bio"
            class="text-surface-700 dark:text-surface-300 text-center whitespace-pre-line max-w-2xl"
          >
            {{ profile.bio }}
          </p>

          <!-- Action buttons -->
          <div class="flex items-center justify-center gap-2.5">
            <Button
              v-if="isOwnProfile"
              label="Modifier le profil"
              icon="pi pi-pencil"
              rounded
              @click="showEditProfileModal = true"
            />
            <Button
              v-else-if="canContact"
              label="Contacter"
              icon="pi pi-envelope"
              rounded
              severity="info"
              @click="handleContact"
            />
          </div>
        </div>
      </section>

      <!-- Social links -->
      <div v-if="hasSocialLinks || isOwnProfile" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">
            Liens sociaux
          </h2>
          <Button
            v-if="isOwnProfile"
            icon="pi pi-pencil"
            text
            rounded
            @click="showEditSocialLinksModal = true"
          />
        </div>
        <div v-if="hasSocialLinks" class="flex flex-wrap gap-3">
          <a
            v-for="link in profile.social_links"
            :key="link.url"
            :href="link.url"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-surface-100 dark:bg-surface-800 hover:bg-surface-200 dark:hover:bg-surface-700 transition-colors text-surface-700 dark:text-surface-300"
          >
            <i :class="['pi', getPlatformIcon(link.platform)]" />
            <span>{{ link.platform_label }}</span>
          </a>
        </div>
        <p v-else class="text-surface-500 dark:text-surface-400 text-sm">
          Vous n'avez pas encore ajouté de liens sociaux.
        </p>
      </div>

      <!-- Musician announces -->
      <div v-if="hasMusicianAnnounces || isOwnProfile" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
          Annonces musicien
        </h2>

        <!-- CTA when own profile with no announces -->
        <div v-if="!hasMusicianAnnounces && isOwnProfile" class="flex flex-col items-center justify-center py-8 text-center">
          <div class="flex items-center justify-center w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900/30 mb-4">
            <i class="pi pi-megaphone text-2xl text-primary-600 dark:text-primary-400" />
          </div>
          <p class="text-surface-500 dark:text-surface-400 mb-4">
            Vous n'avez pas d'annonce
          </p>
          <Button
            label="Ajouter une annonce"
            icon="pi pi-plus"
            @click="$router.push({ name: 'app_user_announces' })"
          />
        </div>

        <!-- Announces list -->
        <div v-else class="flex flex-col gap-4">
          <div
            v-for="announce in profile.musician_announces"
            :key="announce.id"
            class="flex flex-col md:flex-row md:items-center gap-3 p-4 rounded-lg bg-surface-50 dark:bg-surface-800"
          >
            <div class="flex items-center gap-3 md:w-48 shrink-0">
              <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30">
                <i :class="['pi text-lg text-primary-600 dark:text-primary-400', announce.type === TYPES_ANNOUNCE_BAND ? 'pi-users' : 'pi-user']" />
              </div>
              <div>
                <Tag :value="getTypeName(announce.type)" :severity="getTypeSeverity(announce.type)" size="small" />
                <p class="text-sm font-medium text-surface-900 dark:text-surface-0 mt-1">
                  {{ announce.instrument_name }}
                </p>
              </div>
            </div>

            <div class="flex-1">
              <div class="flex flex-wrap gap-1">
                <Tag
                  v-for="style in announce.styles"
                  :key="style"
                  :value="style"
                  severity="info"
                  size="small"
                />
              </div>
            </div>

            <div class="flex items-center gap-4 text-sm text-surface-500 dark:text-surface-400">
              <span class="flex items-center gap-1">
                <i class="pi pi-map-marker text-xs" />
                {{ announce.location_name }}
              </span>
              <span>
                {{ formatDate(announce.creation_datetime) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="messageRecipient"
    />

    <AuthRequiredModal
      v-model:visible="showAuthModal"
      message="Vous devez vous connecter pour envoyer un message à cet utilisateur."
    />

    <!-- Profile picture view modal -->
    <Dialog
      v-model:visible="showProfilePictureViewModal"
      modal
      dismissableMask
      :showHeader="false"
      :pt="{
        root: { class: 'bg-transparent shadow-none' },
        content: { class: 'p-0 bg-transparent' }
      }"
    >
      <img
        v-if="profile?.profile_picture_large_url"
        :src="profile.profile_picture_large_url"
        :alt="profile.username"
        class="w-[300px] h-[300px] md:w-[400px] md:h-[400px] rounded-full object-cover"
      />
    </Dialog>

    <!-- Edit profile modal -->
    <EditProfileModal
      v-model:visible="showEditProfileModal"
      :initial-bio="profile?.bio"
      :initial-location="profile?.location"
      :initial-is-public="profile?.is_public ?? true"
      @saved="handleProfileSaved"
    />

    <!-- Edit social links modal -->
    <EditSocialLinksModal
      v-model:visible="showEditSocialLinksModal"
      @changed="handleSocialLinksChanged"
    />

    <!-- Cover picture cropper modal -->
    <CoverPictureModal
      v-model:visible="showCoverPictureModal"
      :image="coverPictureImage"
      @saved="handleCoverPictureSaved"
    />

    <!-- Profile picture cropper modal -->
    <ProfilePictureModal
      v-model:visible="showProfilePictureEditModal"
      :image="profilePictureImage"
      @saved="handleProfilePictureSaved"
    />

    <!-- Hidden file inputs -->
    <input
      ref="coverPictureInputRef"
      type="file"
      accept="image/*"
      class="hidden"
      @change="handleCoverPictureSelect"
    />
    <input
      ref="profilePictureInputRef"
      type="file"
      accept="image/*"
      class="hidden"
      @change="handleProfilePictureSelect"
    />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import EditProfileModal from '../../../components/User/Profile/EditProfileModal.vue'
import EditSocialLinksModal from '../../../components/User/Profile/EditSocialLinksModal.vue'
import CoverPictureModal from '../Settings/CoverPictureModal.vue'
import ProfilePictureModal from '../Settings/ProfilePictureModal.vue'
import { TYPES_ANNOUNCE_BAND } from '../../../constants/types.js'
import { useUserProfileStore } from '../../../store/user/profile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { getAvatarStyle } from '../../../utils/avatar.js'

const route = useRoute()
const userProfileStore = useUserProfileStore()
const userSecurityStore = useUserSecurityStore()

const isLoading = ref(true)
const notFound = ref(false)
const isPrivate = ref(false)
const showMessageModal = ref(false)
const showAuthModal = ref(false)
const showProfilePictureViewModal = ref(false)
const showEditProfileModal = ref(false)
const showEditSocialLinksModal = ref(false)
const showCoverPictureModal = ref(false)
const showProfilePictureEditModal = ref(false)

// Picture editing
const coverPictureInputRef = ref(null)
const profilePictureInputRef = ref(null)
const coverPictureImage = ref(null)
const profilePictureImage = ref(null)

const profile = computed(() => userProfileStore.profile)

const isOwnProfile = computed(() => {
  if (!profile.value || !userSecurityStore.userProfile) return false
  return userSecurityStore.userProfile.id === profile.value.user_id
})

const hasSocialLinks = computed(() => {
  return profile.value?.social_links && profile.value.social_links.length > 0
})

const hasMusicianAnnounces = computed(() => {
  return profile.value?.musician_announces && profile.value.musician_announces.length > 0
})

const messageRecipient = computed(() => {
  if (!profile.value) return null
  return {
    id: profile.value.user_id,
    username: profile.value.username
  }
})

const canContact = computed(() => {
  if (!profile.value || !userSecurityStore.userProfile) return true
  return userSecurityStore.userProfile.id !== profile.value.user_id
})

const pageTitle = computed(() => {
  if (profile.value) {
    return `${profile.value.username} - MusicAll`
  }
  return 'Profil - MusicAll'
})

useTitle(pageTitle)

const platformIcons = {
  youtube: 'pi-youtube',
  soundcloud: 'pi-cloud',
  instagram: 'pi-instagram',
  facebook: 'pi-facebook',
  twitter: 'pi-twitter',
  tiktok: 'pi-video',
  spotify: 'pi-spotify',
  bandcamp: 'pi-headphones',
  website: 'pi-globe'
}

function getPlatformIcon(platform) {
  return platformIcons[platform] || 'pi-link'
}

function formatMemberSince(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('fr-FR', {
    month: 'long',
    year: 'numeric'
  })
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

function getTypeName(type) {
  return type === TYPES_ANNOUNCE_BAND ? 'Groupe' : 'Musicien'
}

function getTypeSeverity(type) {
  return type === TYPES_ANNOUNCE_BAND ? 'success' : 'info'
}

function handleContact() {
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  showMessageModal.value = true
}

async function loadProfile() {
  isLoading.value = true
  notFound.value = false
  isPrivate.value = false

  try {
    await userProfileStore.loadProfile(route.params.username)
  } catch (error) {
    if (error.response?.status === 404) {
      notFound.value = true
    } else if (error.response?.status === 403) {
      isPrivate.value = true
    } else {
      notFound.value = true
    }
  } finally {
    isLoading.value = false
  }
}

// Picture selection handlers
function handleCoverPictureSelect(event) {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    coverPictureImage.value = e.target.result
    showCoverPictureModal.value = true
  }
  reader.readAsDataURL(file)

  // Reset input so the same file can be selected again
  event.target.value = ''
}

function handleProfilePictureSelect(event) {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    profilePictureImage.value = e.target.result
    showProfilePictureEditModal.value = true
  }
  reader.readAsDataURL(file)

  // Reset input so the same file can be selected again
  event.target.value = ''
}

// Save handlers
function handleProfileSaved() {
  loadProfile()
}

function handleSocialLinksChanged() {
  loadProfile()
}

function handleCoverPictureSaved() {
  loadProfile()
}

function handleProfilePictureSaved() {
  // ProfilePictureModal already refreshes the navbar avatar via userSettingsStore
  loadProfile()
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
  userProfileStore.clear()
})
</script>
