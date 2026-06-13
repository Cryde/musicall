<template>
  <div>
    <HomeHero />

    <HomePublications
      :publications="publicationsStore.lastPublications"
      :is-loading="isLoadingPublications"
      @open-discover-modal="handleOpenDiscoverModal"
    />

    <HomeAnnounces
      :announces="musicianAnnounceStore.lastAnnounces"
      :is-loading="isLoadingAnnounces"
      @open-announce-modal="handleOpenAnnounceModal"
      @contact-announce="handleContactAnnounce"
    />

    <!-- CTA Section - Cosmic Style -->
    <section v-if="!userSecurityStore.isAuthenticated" class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-surface-950 via-primary-950/50 to-surface-950 dark:from-surface-950 dark:via-primary-950/50 dark:to-surface-950">
      <!-- Light mode background -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-100 via-purple-50 to-primary-100 dark:hidden" />

      <!-- Gradient orbs -->
      <div class="absolute top-10 left-1/4 w-48 h-48 bg-primary-600/30 dark:bg-primary-600/30 rounded-full blur-[80px] animate-drift" />
      <div class="absolute bottom-10 right-1/4 w-64 h-64 bg-fuchsia-600/20 dark:bg-fuchsia-600/20 rounded-full blur-[100px] animate-drift" style="animation-delay: -5s;" />

      <!-- Floating particles -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-[20%] right-[15%] w-3 h-3 bg-primary-400 rounded-full animate-glow shadow-lg shadow-primary-400/50" />
        <div class="absolute top-[60%] left-[20%] w-2 h-2 bg-fuchsia-400 rounded-full animate-glow shadow-lg shadow-fuchsia-400/50" style="animation-delay: -1s;" />
        <div class="absolute top-[40%] right-[25%] w-2 h-2 bg-cyan-400 rounded-full animate-glow shadow-lg shadow-cyan-400/50" style="animation-delay: -2s;" />
        <div class="absolute top-[70%] right-[40%] w-1 h-1 bg-white rounded-full animate-particle-slow" />
        <div class="absolute top-[30%] left-[30%] w-1 h-1 bg-white/80 rounded-full animate-particle-slow" style="animation-delay: -2s;" />
        <div class="absolute top-[80%] left-[15%] w-1.5 h-1.5 bg-primary-300/80 rounded-full animate-particle" style="animation-delay: -3s;" />
        <div class="absolute top-[15%] left-[40%] w-1 h-1 bg-white rounded-full animate-particle-slow" style="animation-delay: -4s;" />
      </div>

      <!-- Content -->
      <div class="relative z-10 text-center py-16 px-8">
        <h2 class="text-3xl md:text-4xl font-bold mb-4">
          <span class="text-surface-900 dark:text-white">Rejoignez la communauté </span>
          <span class="bg-gradient-to-r from-primary-600 via-fuchsia-500 to-cyan-500 dark:from-primary-400 dark:via-fuchsia-400 dark:to-cyan-400 bg-clip-text text-transparent">MusicAll</span>
        </h2>
        <p class="text-lg text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
          Des milliers de passionné·e·s échangent déjà sur MusicAll
        </p>
        <router-link :to="{ name: 'app_register' }">
          <Button
            label="Créer mon compte gratuitement"
            icon="pi pi-user-plus"
            size="large"
            rounded
            class="!bg-gradient-to-r !from-[#6b97be] !to-[#4a7599] dark:!from-[#7ba7ce] dark:!to-[#5b87ae] hover:!from-[#5b87ae] hover:!to-[#3a6589] dark:hover:!from-[#8bb7de] dark:hover:!to-[#6b97be] !shadow-xl !shadow-[#5b87ae]/30 dark:!shadow-[#5b87ae]/40 hover:!shadow-[#5b87ae]/50 hover:!-translate-y-1 !transition-all !duration-300"
          />
        </router-link>
      </div>
    </section>

    <!-- Modals -->
    <AddDiscoverModal @published="handleDiscoverPublished" />
    <SendMessageModal v-model:visible="showMessageModal" :selected-recipient="selectedRecipient" />
    <AuthRequiredModal v-model:visible="showAuthModal" :message="authModalMessage" />
    <AddAnnounceModal v-model:visible="showAnnounceModal" @created="handleAnnounceCreated" />
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import Button from 'primevue/button'
import { onMounted, onUnmounted, ref } from 'vue'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import HomeAnnounces from '../../components/Home/HomeAnnounces.vue'
import HomeHero from '../../components/Home/HomeHero.vue'
import HomePublications from '../../components/Home/HomePublications.vue'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import AddDiscoverModal from '../../components/Publication/AddDiscoverModal.vue'
import { useMusicianAnnounceStore } from '../../store/announce/musician.js'
import { usePublicationsStore } from '../../store/publication/publications.js'
import { useVideoStore } from '../../store/publication/video.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import AddAnnounceModal from '../User/Announce/AddAnnounceModal.vue'

useTitle('MusicAll, le site de référence au service de la musique')

const publicationsStore = usePublicationsStore()
const musicianAnnounceStore = useMusicianAnnounceStore()
const videoStore = useVideoStore()
const userSecurityStore = useUserSecurityStore()

const isLoadingPublications = ref(true)
const isLoadingAnnounces = ref(true)
const showAuthModal = ref(false)
const showMessageModal = ref(false)
const showAnnounceModal = ref(false)
const selectedRecipient = ref(null)
const authModalMessage = ref('')

onMounted(async () => {
  await Promise.all([
    (async () => {
      isLoadingPublications.value = true
      await publicationsStore.loadLastPublications()
      isLoadingPublications.value = false
    })(),
    (async () => {
      isLoadingAnnounces.value = true
      await musicianAnnounceStore.loadLastAnnounces()
      isLoadingAnnounces.value = false
    })()
  ])
})

function handleOpenDiscoverModal() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value =
      'Si vous souhaitez partager une vidéo avec la communauté, vous devez vous connecter.'
    showAuthModal.value = true
    return
  }
  videoStore.openModal()
}

function handleOpenAnnounceModal() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Si vous souhaitez poster une annonce, vous devez vous connecter.'
    showAuthModal.value = true
    return
  }
  showAnnounceModal.value = true
}

async function handleAnnounceCreated() {
  await musicianAnnounceStore.loadLastAnnounces()
}

function handleContactAnnounce(author) {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Vous devez vous connecter pour envoyer un message à cet utilisateur.'
    showAuthModal.value = true
    return
  }
  selectedRecipient.value = author
  showMessageModal.value = true
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
