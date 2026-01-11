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
      <div class="flex items-center gap-4">
        <Button
          icon="pi pi-arrow-left"
          severity="secondary"
          text
          rounded
          @click="$router.push({ name: 'app_user_public_profile', params: { username: profile.username } })"
        />
        <Avatar
          v-if="profile.profile_picture_url"
          :image="profile.profile_picture_url"
          size="xlarge"
          shape="circle"
        />
        <Avatar
          v-else
          :label="profile.username.charAt(0).toUpperCase()"
          :style="getAvatarStyle(profile.username)"
          size="xlarge"
          shape="circle"
        />
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">
            Profil musicien
          </h1>
          <p class="text-surface-500 dark:text-surface-400">
            @{{ profile.username }}
          </p>
        </div>
      </div>

      <!-- Availability status -->
      <div v-if="profile.availability_status" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
          Disponibilité
        </h2>
        <Tag
          :value="profile.availability_status_label"
          :severity="getAvailabilitySeverity(profile.availability_status)"
          size="large"
        />
      </div>

      <!-- Instruments -->
      <div v-if="profile.instruments?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
          Instruments
        </h2>
        <div class="flex flex-col gap-3">
          <div
            v-for="instrument in profile.instruments"
            :key="instrument.instrument_id"
            class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
          >
            <span class="font-medium text-surface-900 dark:text-surface-0">
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
      <div v-if="profile.styles?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
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

      <!-- Musician Announces -->
      <div v-if="profile.musician_announces?.length" class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 mb-4">
          Annonces
        </h2>
        <div class="flex flex-col gap-4">
          <MusicianAnnounceItem
            v-for="announce in profile.musician_announces"
            :key="announce.id"
            :announce="announce"
          />
        </div>
      </div>

      <!-- Edit button for own profile -->
      <div v-if="isOwnProfile" class="flex justify-center">
        <Button
          label="Modifier mon profil musicien"
          icon="pi pi-pencil"
          @click="showEditModal = true"
        />
      </div>
    </div>

    <!-- Edit modal -->
    <EditMusicianProfileModal
      v-model:visible="showEditModal"
      :musician-profile="profile"
      @saved="handleProfileSaved"
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
import { useRoute } from 'vue-router'
import EditMusicianProfileModal from '../../../components/User/Profile/EditMusicianProfileModal.vue'
import MusicianAnnounceItem from '../../../components/User/Profile/MusicianAnnounceItem.vue'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { getAvatarStyle } from '../../../utils/avatar.js'

const route = useRoute()
const musicianProfileStore = useMusicianProfileStore()
const userSecurityStore = useUserSecurityStore()

const isLoading = ref(true)
const notFound = ref(false)
const showEditModal = ref(false)

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
