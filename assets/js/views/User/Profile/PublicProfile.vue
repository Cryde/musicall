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
        {{ t('profile.public.not_found_title') }}
      </h2>
      <p class="text-surface-500 dark:text-surface-400 mb-4">
        {{ t('profile.public.not_found_help') }}
      </p>
      <Button
        :label="t('profile.public.back_home')"
        icon="pi pi-home"
        severity="info"
        @click="$router.push({ name: 'app_home' })"
      />
    </div>

    <!-- Private profile state -->
    <PrivateProfileNotice v-else-if="isPrivate" :username="route.params.username" />

    <!-- Profile content (#1317): who they are, the wall, what else they publish -->
    <div
      v-else-if="profile"
      class="flex flex-col gap-5 lg:grid lg:items-start lg:grid-cols-[280px_minmax(0,1fr)] xl:grid-cols-[290px_minmax(0,1fr)_300px]"
    >
      <template v-if="isDesktop">
        <!-- Sticky beside the wall only when it does not also carry the right column's cards. -->
        <!-- The cards keep their height: the column scrolls instead of squeezing them. -->
        <div class="flex flex-col gap-4 *:shrink-0 xl:sticky xl:top-6 xl:max-h-[calc(100vh-3rem)] xl:overflow-y-auto">
          <ProfileIdentityCard v-bind="identityProps" v-on="identityEvents" />
          <ProfileActivitiesCard v-bind="activitiesProps" v-on="activitiesEvents" />
          <ProfileInstrumentsCard :instruments="profile.musician?.instruments ?? []" />
          <ProfileStylesCard :styles="profile.musician?.styles ?? []" />
          <ProfileLinksCard v-bind="linksProps" @edit="showEditSocialLinksModal = true" />
          <template v-if="!isWide">
            <ProfileListenCard v-bind="listenProps" />
            <ProfileAnnouncesCard v-bind="announcesProps" compact @contact="handleContactAbout" />
            <ProfileCoursesCard :username="profile.username" />
          </template>
        </div>
        <ProfileWall v-bind="wallProps" />
        <div v-if="isWide" class="flex flex-col gap-4 *:shrink-0 sticky top-6 max-h-[calc(100vh-3rem)] overflow-y-auto">
          <ProfileListenCard v-bind="listenProps" />
          <ProfileAnnouncesCard v-bind="announcesProps" compact @contact="handleContactAbout" />
          <ProfileCoursesCard :username="profile.username" />
        </div>
      </template>

      <!-- Phone and tablet: the header, then the wall, then the rest. -->
      <template v-else>
        <ProfileIdentityCard v-bind="identityProps" v-on="identityEvents" />
        <ProfileActivitiesCard v-bind="activitiesProps" v-on="activitiesEvents" />
        <ProfileWall v-bind="wallProps" />
        <ProfileListenCard v-bind="listenProps" />
        <ProfileInstrumentsCard :instruments="profile.musician?.instruments ?? []" />
        <ProfileStylesCard :styles="profile.musician?.styles ?? []" />
        <ProfileLinksCard v-bind="linksProps" @edit="showEditSocialLinksModal = true" />
        <ProfileAnnouncesCard v-bind="announcesProps" @contact="handleContactAbout" />
        <ProfileCoursesCard :username="profile.username" />
      </template>
    </div>

    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="messageRecipient"
      :contact-origin="contactOrigin"
    />

    <AuthRequiredModal
      v-model:visible="showAuthModal"
      :message="t('profile.common.contact_auth_required')"
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
      :initial-display-name="profile?.display_name"
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

    <!-- Create musician profile modal -->
    <EditMusicianProfileModal
      v-model:visible="showCreateMusicianProfileModal"
      :musician-profile="null"
      @saved="handleMusicianProfileCreated"
    />

    <!-- Create teacher profile modal -->
    <EditTeacherProfileModal
      v-model:visible="showCreateTeacherProfileModal"
      :teacher-profile="null"
      @saved="handleTeacherProfileCreated"
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
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { useMediaQuery, useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import ProfileActivitiesCard from '../../../components/Profile/Page/ProfileActivitiesCard.vue'
import ProfileAnnouncesCard from '../../../components/Profile/Page/ProfileAnnouncesCard.vue'
import ProfileCoursesCard from '../../../components/Profile/Page/ProfileCoursesCard.vue'
import ProfileIdentityCard from '../../../components/Profile/Page/ProfileIdentityCard.vue'
import ProfileInstrumentsCard from '../../../components/Profile/Page/ProfileInstrumentsCard.vue'
import ProfileLinksCard from '../../../components/Profile/Page/ProfileLinksCard.vue'
import ProfileListenCard from '../../../components/Profile/Page/ProfileListenCard.vue'
import ProfileStylesCard from '../../../components/Profile/Page/ProfileStylesCard.vue'
import ProfileWall from '../../../components/Profile/Wall/ProfileWall.vue'
import EditTeacherProfileModal from '../../../components/Teacher/EditTeacherProfileModal.vue'
import EditMusicianProfileModal from '../../../components/User/Profile/EditMusicianProfileModal.vue'
import EditProfileModal from '../../../components/User/Profile/EditProfileModal.vue'
import EditSocialLinksModal from '../../../components/User/Profile/EditSocialLinksModal.vue'
import PrivateProfileNotice from '../../../components/User/Profile/PrivateProfileNotice.vue'
import { useUserBlockStore } from '../../../store/user/block.js'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import { useUserProfileStore } from '../../../store/user/profile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { useTeacherProfileStore } from '../../../store/user/teacherProfile.js'
import { isPrivateProfileError } from '../../../utils/profileError.js'
import CoverPictureModal from '../Settings/CoverPictureModal.vue'
import ProfilePictureModal from '../Settings/ProfilePictureModal.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()
const userProfileStore = useUserProfileStore()
const userSecurityStore = useUserSecurityStore()
const userBlockStore = useUserBlockStore()
const musicianProfileStore = useMusicianProfileStore()
const teacherProfileStore = useTeacherProfileStore()

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
const showCreateMusicianProfileModal = ref(false)
const showCreateTeacherProfileModal = ref(false)

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

// Three columns from xl, two from lg (the right column's cards then close the left one), one below (#1317).
const isDesktop = useMediaQuery('(min-width: 1024px)')
const isWide = useMediaQuery('(min-width: 1280px)')

const contactOrigin = ref(null)
const messageRecipient = computed(() => {
  if (!profile.value) return null
  return {
    id: profile.value.user_id,
    username: profile.value.username
  }
})

const canContact = computed(() => {
  if (!profile.value || !userSecurityStore.userProfile) return true
  // A block closes the conversation both ways; the « Débloquer » next to it is the way back.
  return (
    userSecurityStore.userProfile.id !== profile.value.user_id &&
    !userBlockStore.isBlocked(profile.value.user_id)
  )
})

const identityProps = computed(() => ({
  profile: profile.value,
  isOwner: isOwnProfile.value,
  canContact: canContact.value
}))
const identityEvents = {
  back: handleBack,
  contact: handleContact,
  'edit-profile': () => {
    showEditProfileModal.value = true
  },
  'edit-cover': () => coverPictureInputRef.value?.click(),
  'edit-picture': () => profilePictureInputRef.value?.click(),
  'view-picture': () => {
    showProfilePictureViewModal.value = true
  }
}
const activitiesProps = computed(() => ({ profile: profile.value, isOwner: isOwnProfile.value }))
const activitiesEvents = {
  'create-musician': () => {
    showCreateMusicianProfileModal.value = true
  },
  'create-teacher': () => {
    showCreateTeacherProfileModal.value = true
  }
}
const linksProps = computed(() => ({
  links: profile.value?.social_links ?? [],
  isOwner: isOwnProfile.value
}))
const announcesProps = computed(() => ({
  announces: profile.value?.musician_announces ?? [],
  isOwner: isOwnProfile.value
}))
const listenProps = computed(() => ({
  profile: profile.value,
  displayName: profile.value.display_name || profile.value.username
}))
const wallProps = computed(() => ({
  username: profile.value.username,
  displayName: profile.value.display_name || profile.value.username,
  pictureUrl: profile.value.profile_picture_url,
  isOwner: isOwnProfile.value
}))

const pageTitle = computed(() => {
  if (profile.value) {
    return t('profile.public.page_title', { username: profile.value.username })
  }
  return t('profile.public.page_title_default')
})

useTitle(pageTitle)

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
    default:
      // Fallback: try back, or go to home
      if (window.history.length > 1) {
        router.back()
      } else {
        router.push({ name: 'app_home' })
      }
  }
}

function handleContact() {
  openMessage(null)
}

function handleContactAbout(announce) {
  openMessage({ musicianAnnounceId: announce.id })
}

function openMessage(origin) {
  trackUmamiEvent('profile-contact-click')
  if (!userSecurityStore.isAuthenticated) {
    showAuthModal.value = true
    return
  }
  contactOrigin.value = origin
  showMessageModal.value = true
}

async function loadProfile() {
  isLoading.value = true
  notFound.value = false
  isPrivate.value = false

  try {
    await userProfileStore.loadProfile(route.params.username)
    trackUmamiEvent('profile-view')
  } catch (error) {
    isPrivate.value = isPrivateProfileError(error)
    notFound.value = !isPrivate.value
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

function handleMusicianProfileCreated() {
  loadProfile()
  musicianProfileStore.clear()
  toast.add({
    severity: 'success',
    summary: t('profile.public.profile_created'),
    detail: t('profile.public.musician_profile_created'),
    life: 5000
  })
}

function handleTeacherProfileCreated() {
  loadProfile()
  teacherProfileStore.clear()
  toast.add({
    severity: 'success',
    summary: t('profile.public.profile_created'),
    detail: t('profile.public.teacher_profile_created'),
    life: 5000
  })
}

watch(
  () => route.params.username,
  (newUsername) => {
    if (newUsername) {
      loadProfile()
    }
  }
)

onMounted(() => {
  loadProfile()
})

onUnmounted(() => {
  userProfileStore.clear()
})
</script>
