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

    <template v-else-if="profile">
      <div
        v-if="isOwnProfile && viewAsVisitor"
        class="mb-5 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 rounded-xl px-4 py-2.5 text-sm bg-surface-900 text-surface-0 dark:bg-surface-0 dark:text-surface-900"
      >
        <span class="inline-flex items-center gap-2 font-semibold">
          <i class="pi pi-eye" aria-hidden="true" />{{ t('profile.owner.previewing') }}
        </span>
        <button
          ref="backToOwnerButton"
          type="button"
          class="font-semibold underline underline-offset-2 cursor-pointer"
          @click="leavePreview"
        >
          {{ t('profile.owner.back_to_owner') }}
        </button>
      </div>

      <!-- Profile content (#1317): who they are, the wall, what else they publish -->
      <div
        class="flex flex-col gap-5 lg:grid lg:items-start lg:grid-cols-[280px_minmax(0,1fr)] xl:grid-cols-[290px_minmax(0,1fr)_300px]"
      >
        <template v-if="isDesktop">
          <!-- Sticky beside the wall only when it does not also carry the right column's cards. -->
          <!-- The cards keep their height: the column scrolls instead of squeezing them. -->
          <div class="flex flex-col gap-4 *:shrink-0 xl:sticky xl:top-6 xl:max-h-[calc(100vh-3rem)] xl:overflow-y-auto">
            <ProfileIdentityCard ref="identityCardRef" v-bind="identityProps" v-on="identityEvents" />
            <ProfileActivitiesCard v-bind="activitiesProps" v-on="activitiesEvents" />
            <ProfileInstrumentsCard v-bind="instrumentsProps" @edit="openMusicianBlock('instruments')" />
            <ProfileBandsCard v-bind="bandsProps" @edit="openMusicianBlock('bands')" />
            <ProfileStylesCard v-bind="stylesProps" @edit="openMusicianBlock('styles')" />
            <ProfileLinksCard v-bind="linksProps" @edit="showEditSocialLinksModal = true" />
            <template v-if="!isWide">
              <ProfileListenCard v-bind="listenProps" @edit="showMedia" />
              <ProfileAnnouncesCard v-bind="announcesProps" compact @contact="handleContactAbout" />
              <ProfileCoursesCard :username="profile.username" />
            </template>
          </div>
          <div class="flex flex-col gap-4 min-w-0">
            <ProfileOwnerBanner v-if="showOwnerTools" />
            <ProfileWall ref="wallRef" v-bind="wallProps" @media-changed="refreshProfile" />
          </div>
          <div v-if="isWide" class="flex flex-col gap-4 *:shrink-0 sticky top-6 max-h-[calc(100vh-3rem)] overflow-y-auto">
            <ProfileListenCard v-bind="listenProps" @edit="showMedia" />
            <ProfileAnnouncesCard v-bind="announcesProps" compact @contact="handleContactAbout" />
            <ProfileCoursesCard :username="profile.username" />
          </div>
        </template>

        <!-- Phone and tablet: the header, then the wall, then the rest. -->
        <template v-else>
          <ProfileIdentityCard ref="identityCardRef" v-bind="identityProps" v-on="identityEvents" />
          <ProfileActivitiesCard v-bind="activitiesProps" v-on="activitiesEvents" />
          <ProfileOwnerBanner v-if="showOwnerTools" />
          <ProfileWall ref="wallRef" v-bind="wallProps" @media-changed="refreshProfile" />
          <ProfileListenCard v-bind="listenProps" @edit="showMedia" />
          <ProfileInstrumentsCard v-bind="instrumentsProps" @edit="openMusicianBlock('instruments')" />
          <ProfileBandsCard v-bind="bandsProps" @edit="openMusicianBlock('bands')" />
          <ProfileStylesCard v-bind="stylesProps" @edit="openMusicianBlock('styles')" />
          <ProfileLinksCard v-bind="linksProps" @edit="showEditSocialLinksModal = true" />
          <ProfileAnnouncesCard v-bind="announcesProps" @contact="handleContactAbout" />
          <ProfileCoursesCard :username="profile.username" />
        </template>
      </div>
    </template>

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
      @saved="refreshProfile"
    />

    <!-- Edit social links modal -->
    <EditSocialLinksModal
      v-model:visible="showEditSocialLinksModal"
      @changed="refreshProfile"
    />

    <!-- Cover picture cropper modal -->
    <CoverPictureModal
      v-model:visible="showCoverPictureModal"
      :image="coverPictureImage"
      @saved="refreshProfile"
    />

    <!-- Profile picture cropper modal -->
    <ProfilePictureModal
      v-model:visible="showProfilePictureEditModal"
      :image="profilePictureImage"
      @saved="refreshProfile"
    />

    <!-- Create musician profile modal -->
    <EditMusicianProfileModal
      v-model:visible="showCreateMusicianProfileModal"
      :musician-profile="null"
      @saved="handleMusicianProfileCreated"
    />

    <!-- One block of the musician profile, from its pencil -->
    <EditMusicianProfileModal
      v-model:visible="showMusicianBlockModal"
      :musician-profile="profile?.musician ?? null"
      :block="musicianBlock"
      @saved="refreshProfile"
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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AuthRequiredModal from '../../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../../components/Message/SendMessageModal.vue'
import ProfileActivitiesCard from '../../../components/Profile/Page/ProfileActivitiesCard.vue'
import ProfileAnnouncesCard from '../../../components/Profile/Page/ProfileAnnouncesCard.vue'
import ProfileBandsCard from '../../../components/Profile/Page/ProfileBandsCard.vue'
import ProfileCoursesCard from '../../../components/Profile/Page/ProfileCoursesCard.vue'
import ProfileIdentityCard from '../../../components/Profile/Page/ProfileIdentityCard.vue'
import ProfileInstrumentsCard from '../../../components/Profile/Page/ProfileInstrumentsCard.vue'
import ProfileLinksCard from '../../../components/Profile/Page/ProfileLinksCard.vue'
import ProfileListenCard from '../../../components/Profile/Page/ProfileListenCard.vue'
import ProfileOwnerBanner from '../../../components/Profile/Page/ProfileOwnerBanner.vue'
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
import { listenPlaylist } from '../../../utils/profileListen.js'
import { mediaAddTargets } from '../../../utils/profileOwner.js'
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
const showMusicianBlockModal = ref(false)
const musicianBlock = ref(null)
// The owner looking at their own profile as a visitor would (#1317): no pencils, no composer.
const viewAsVisitor = ref(false)
const backToOwnerButton = ref(null)
const identityCardRef = ref(null)
const wallRef = ref(null)

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

const showOwnerTools = computed(() => isOwnProfile.value && !viewAsVisitor.value)
const musicianEditable = computed(() => showOwnerTools.value && Boolean(profile.value?.musician))

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
  isOwner: showOwnerTools.value,
  canContact: canContact.value,
  previewing: isOwnProfile.value && viewAsVisitor.value
}))
const identityEvents = {
  back: handleBack,
  contact: handleContact,
  'edit-profile': () => {
    showEditProfileModal.value = true
  },
  'view-as-visitor': async () => {
    viewAsVisitor.value = true
    await nextTick()
    backToOwnerButton.value?.focus()
  },
  'edit-cover': () => coverPictureInputRef.value?.click(),
  'edit-picture': () => profilePictureInputRef.value?.click(),
  'view-picture': () => {
    showProfilePictureViewModal.value = true
  }
}
const activitiesProps = computed(() => ({ profile: profile.value, isOwner: showOwnerTools.value }))
const activitiesEvents = {
  'edit-musician': () => openMusicianBlock('availability'),
  'create-musician': () => {
    showCreateMusicianProfileModal.value = true
  },
  'create-teacher': () => {
    showCreateTeacherProfileModal.value = true
  }
}
const instrumentsProps = computed(() => ({
  instruments: profile.value.musician?.instruments ?? [],
  editable: musicianEditable.value
}))
const bandsProps = computed(() => ({
  bands: profile.value.musician?.bands ?? [],
  editable: musicianEditable.value
}))
const stylesProps = computed(() => ({
  styles: profile.value.musician?.styles ?? [],
  editable: musicianEditable.value
}))
const linksProps = computed(() => ({
  links: profile.value?.social_links ?? [],
  isOwner: showOwnerTools.value
}))
const announcesProps = computed(() => ({
  announces: profile.value?.musician_announces ?? [],
  isOwner: showOwnerTools.value,
  contactable: !isOwnProfile.value
}))
const listenProps = computed(() => ({
  profile: profile.value,
  displayName: profile.value.display_name || profile.value.username,
  editable: showOwnerTools.value && Boolean(profile.value.musician || profile.value.teacher)
}))
const wallProps = computed(() => ({
  username: profile.value.username,
  displayName: profile.value.display_name || profile.value.username,
  pictureUrl: profile.value.profile_picture_url,
  isOwner: showOwnerTools.value,
  media: listenPlaylist(profile.value),
  mediaAddTargets: showOwnerTools.value ? mediaAddTargets(profile.value) : [],
  countsMusicianView: !isOwnProfile.value && Boolean(profile.value.musician)
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
  viewAsVisitor.value = false

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

// After the owner's own edit: the page updates in place, the wall keeps its scroll and filter. Going private from
// the identity dialog shows the private notice; any other failure keeps what is shown, the change being saved.
async function refreshProfile() {
  try {
    await userProfileStore.loadProfile(route.params.username)
  } catch (error) {
    isPrivate.value = isPrivateProfileError(error)
  }
}

function openMusicianBlock(block) {
  musicianBlock.value = block
  showMusicianBlockModal.value = true
}

// « Écouter »'s pencil: the media are added and removed from the wall's « Médias ».
async function showMedia() {
  await router.replace({ query: { ...route.query, voir: 'medias' } })
  wallRef.value?.show()
}

async function leavePreview() {
  viewAsVisitor.value = false
  await nextTick()
  identityCardRef.value?.focusViewAsVisitor()
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

function handleMusicianProfileCreated() {
  refreshProfile()
  musicianProfileStore.clear()
  toast.add({
    severity: 'success',
    summary: t('profile.public.profile_created'),
    detail: t('profile.public.musician_profile_created'),
    life: 5000
  })
}

function handleTeacherProfileCreated() {
  refreshProfile()
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
