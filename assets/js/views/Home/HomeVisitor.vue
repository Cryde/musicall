<template>
  <div class="flex flex-col gap-10 sm:gap-16 lg:gap-24">
    <HomeHero :preview-announces="heroAnnounces" :frequent-searches="frequentSearches" />

    <HomeAnnounces
      v-model:filter="announceFilter"
      :announces="musicianAnnounceStore.lastAnnounces"
      :is-loading="!hasLoadedAnnounces || musicianAnnounceStore.isLoadingLast"
      @open-announce-modal="handleOpenAnnounceModal"
      @contact-announce="handleContactAnnounce"
    />

    <HomeBandSpace />

    <HomePublications
      :publications="publicationsStore.lastPublications"
      :is-loading="isLoadingPublications"
      @open-discover-modal="handleOpenDiscoverModal"
    />

    <HomeTeachers :teachers="teachers" @create-teacher-profile="handleCreateTeacherProfile" />

    <section
      v-if="!userSecurityStore.isAuthenticated"
      class="flex flex-col items-center gap-4 rounded-3xl border border-surface-200 dark:border-surface-800 bg-surface-0 dark:bg-surface-900 bg-brand-halo [--halo-at:50%_0%] px-5 py-10 sm:px-6 sm:py-16 text-center"
    >
      <h2 class="m-0 text-2xl sm:text-3xl md:text-4xl font-semibold tracking-tight">
        <i18n-t keypath="home.visitor.cta.title" tag="span" class="text-surface-900 dark:text-surface-0">
          <template #brand>
            <span class="text-primary">{{ t('home.visitor.cta.brand') }}</span>
          </template>
        </i18n-t>
      </h2>
      <p class="m-0 max-w-md text-base sm:text-lg text-surface-600 dark:text-surface-300">
        {{ t('home.visitor.cta.subtitle') }}
      </p>
      <!-- On a phone the large button with its icon wrapped its label under the icon: full width, no icon. -->
      <Button
        as="router-link"
        :to="signInRouteFrom('app_register', route)"
        :label="t('home.visitor.cta.button')"
        :icon="isPhone ? undefined : 'pi pi-user-plus'"
        :size="isPhone ? undefined : 'large'"
        class="mt-4 max-sm:w-full"
      />
    </section>

    <!-- Modals -->
    <AddDiscoverModal @published="handleDiscoverPublished" />
    <SendMessageModal
      v-if="showMessageModal"
      v-model:visible="showMessageModal"
      :selected-recipient="selectedRecipient"
      :contact-origin="selectedContactOrigin"
      @sign-in-required="handleContactSignInRequired"
    />
    <AuthRequiredModal v-if="showAuthModal" v-model:visible="showAuthModal" :message="authModalMessage" />
    <AddAnnounceModal v-if="showAnnounceModal" v-model:visible="showAnnounceModal" @created="handleAnnounceCreated" />
  </div>
</template>

<script setup>
/** The homepage as a visitor sees it (#1074). */
import Button from 'primevue/button'
import { defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import musicianSearchApi from '../../api/search/musician.js'
import teacherProfileApi from '../../api/user/teacherProfile.js'
import HomeAnnounces from '../../components/Home/HomeAnnounces.vue'
import HomeBandSpace from '../../components/Home/HomeBandSpace.vue'
import HomeHero from '../../components/Home/HomeHero.vue'
import HomePublications from '../../components/Home/HomePublications.vue'
import HomeTeachers from '../../components/Home/HomeTeachers.vue'
import AddDiscoverModal from '../../components/Publication/AddDiscoverModal.vue'
import { useKeepContactDraft } from '../../composables/useContactDraft.js'
import { useIsPhone } from '../../composables/useIsPhone.js'
import { displayName } from '../../helper/user/displayName.js'
import { useMusicianAnnounceStore } from '../../store/announce/musician.js'
import { usePublicationsStore } from '../../store/publication/publications.js'
import { useVideoStore } from '../../store/publication/video.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { ANNOUNCE_FILTER_ALL, announceTypeForFilter } from '../../utils/homeSearch.js'
import { signInRouteFrom } from '../../utils/unauthenticatedRedirect.js'

// Heavy modals (message / announce / auth) only appear on a user action — load their
// chunks on demand (paired with v-if below) so they stay out of the initial bundle.
const AuthRequiredModal = defineAsyncComponent(
  () => import('../../components/Auth/AuthRequiredModal.vue')
)
const SendMessageModal = defineAsyncComponent(
  () => import('../../components/Message/SendMessageModal.vue')
)
const AddAnnounceModal = defineAsyncComponent(() => import('../User/Announce/AddAnnounceModal.vue'))

const publicationsStore = usePublicationsStore()
const musicianAnnounceStore = useMusicianAnnounceStore()
const videoStore = useVideoStore()
const userSecurityStore = useUserSecurityStore()
const keepContactDraft = useKeepContactDraft()

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const isPhone = useIsPhone()

const HERO_PREVIEW_COUNT = 3

const isLoadingPublications = ref(true)
const showAuthModal = ref(false)
const showMessageModal = ref(false)
const showAnnounceModal = ref(false)
const selectedRecipient = ref(null)
const selectedContactOrigin = ref(null)
const authModalMessage = ref('')
const announceFilter = ref(ANNOUNCE_FILTER_ALL)
// Taken from the first, unfiltered answer, so the hero keeps its glimpse while the list below is filtered.
const heroAnnounces = ref([])
// The skeleton, rather than « Aucune annonce », until the first answer is in.
const hasLoadedAnnounces = ref(false)
const teachers = ref([])
const frequentSearches = ref([])

onMounted(async () => {
  await Promise.all([
    (async () => {
      isLoadingPublications.value = true
      await publicationsStore.loadLastPublications()
      isLoadingPublications.value = false
    })(),
    (async () => {
      await loadAnnounces()
      hasLoadedAnnounces.value = true
      heroAnnounces.value = musicianAnnounceStore.lastAnnounces
        .slice(0, HERO_PREVIEW_COUNT)
        .reverse()
    })(),
    loadTeachers(),
    loadFrequentSearches()
  ])
})

function loadAnnounces() {
  return musicianAnnounceStore.loadLastAnnounces(announceTypeForFilter(announceFilter.value))
}

watch(announceFilter, loadAnnounces)

// A strip of faces: without them it still stands, so a failure only leaves them out.
async function loadTeachers() {
  try {
    teachers.value = (await teacherProfileApi.getFeaturedTeachers()).member
  } catch {
    teachers.value = []
  }
}

// A row the hero does without: a failure only leaves it out.
async function loadFrequentSearches() {
  try {
    frequentSearches.value = await musicianSearchApi.getFrequentSearches()
  } catch {
    frequentSearches.value = []
  }
}

function handleCreateTeacherProfile() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = t('home.visitor.auth.teacher_profile')
    showAuthModal.value = true
    return
  }
  router.push({ name: 'app_user_settings_profile' })
}

function handleOpenDiscoverModal() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = t('home.visitor.auth.discover')
    showAuthModal.value = true
    return
  }
  videoStore.openModal()
}

function handleOpenAnnounceModal() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = t('home.visitor.auth.announce')
    showAuthModal.value = true
    return
  }
  showAnnounceModal.value = true
}

async function handleAnnounceCreated() {
  await loadAnnounces()
}

// A visitor writes first (#1306): « Envoyer » asks them to sign in, and the member home gives the message back.
function handleContactAnnounce(announce) {
  selectedRecipient.value = announce.author
  selectedContactOrigin.value = { musicianAnnounceId: announce.id }
  showMessageModal.value = true
}

function handleContactSignInRequired({ content }) {
  const isKept = keepContactDraft({
    announceId: selectedContactOrigin.value.musicianAnnounceId,
    recipientId: selectedRecipient.value.id,
    content
  })
  // Promised only when kept: a browser that blocks storage gets the usual prompt.
  authModalMessage.value = isKept
    ? t('search.pending.contact_sign_in', { name: displayName(selectedRecipient.value) })
    : t('home.visitor.auth.contact')
  showAuthModal.value = true
}

async function handleDiscoverPublished() {
  isLoadingPublications.value = true
  await publicationsStore.loadLastPublications()
  isLoadingPublications.value = false
}

onUnmounted(() => {
  publicationsStore.clear()
  musicianAnnounceStore.clear()
})
</script>
