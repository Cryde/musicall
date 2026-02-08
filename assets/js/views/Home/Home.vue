<template>
  <div>
    <!-- Hero Section - Cosmic Particles -->
    <section class="relative overflow-hidden rounded-3xl mb-12 bg-gradient-to-br from-surface-950 via-primary-950/50 to-surface-950 dark:from-surface-950 dark:via-primary-950/50 dark:to-surface-950">
      <!-- Light mode background -->
      <div class="absolute inset-0 bg-gradient-to-br from-primary-100 via-purple-50 to-primary-100 dark:hidden" />

      <!-- Gradient orbs background -->
      <div class="absolute top-20 left-1/4 w-72 h-72 bg-primary-600/30 dark:bg-primary-600/30 rounded-full blur-[100px] animate-drift" />
      <div class="absolute bottom-20 right-1/4 w-96 h-96 bg-fuchsia-600/20 dark:bg-fuchsia-600/20 rounded-full blur-[120px] animate-drift" style="animation-delay: -5s;" />
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-cyan-500/15 dark:bg-cyan-500/15 rounded-full blur-[80px] animate-glow" />

      <!-- Floating particles -->
      <div class="absolute inset-0 overflow-hidden">
        <!-- Large glowing orbs -->
        <div class="absolute top-[15%] right-[20%] w-4 h-4 bg-primary-400 rounded-full animate-glow shadow-lg shadow-primary-400/50" />
        <div class="absolute top-[25%] right-[35%] w-3 h-3 bg-fuchsia-400 rounded-full animate-glow shadow-lg shadow-fuchsia-400/50" style="animation-delay: -1s;" />
        <div class="absolute top-[60%] right-[15%] w-5 h-5 bg-cyan-400 rounded-full animate-glow shadow-lg shadow-cyan-400/50" style="animation-delay: -2s;" />
        <div class="absolute top-[45%] right-[40%] w-2 h-2 bg-white rounded-full animate-glow" style="animation-delay: -0.5s;" />
        <div class="absolute top-[70%] right-[30%] w-3 h-3 bg-primary-300 rounded-full animate-glow shadow-lg shadow-primary-300/50" style="animation-delay: -3s;" />

        <!-- Medium particles -->
        <div class="absolute top-[20%] right-[25%] w-2 h-2 bg-white/60 rounded-full animate-particle" style="animation-delay: -1s;" />
        <div class="absolute top-[35%] right-[18%] w-1.5 h-1.5 bg-primary-300/80 rounded-full animate-particle" style="animation-delay: -3s;" />
        <div class="absolute top-[50%] right-[45%] w-2 h-2 bg-fuchsia-300/70 rounded-full animate-particle" style="animation-delay: -5s;" />
        <div class="absolute top-[65%] right-[22%] w-1.5 h-1.5 bg-cyan-300/80 rounded-full animate-particle" style="animation-delay: -2s;" />
        <div class="absolute top-[80%] right-[38%] w-2 h-2 bg-white/50 rounded-full animate-particle" style="animation-delay: -4s;" />

        <!-- Small star particles -->
        <div class="absolute top-[12%] right-[30%] w-1 h-1 bg-white rounded-full animate-particle-slow" />
        <div class="absolute top-[28%] right-[42%] w-1 h-1 bg-white/80 rounded-full animate-particle-slow" style="animation-delay: -2s;" />
        <div class="absolute top-[42%] right-[12%] w-1 h-1 bg-white rounded-full animate-particle-slow" style="animation-delay: -4s;" />
        <div class="absolute top-[55%] right-[50%] w-1 h-1 bg-white/70 rounded-full animate-particle-slow" style="animation-delay: -1s;" />
        <div class="absolute top-[68%] right-[8%] w-1 h-1 bg-white rounded-full animate-particle-slow" style="animation-delay: -3s;" />
        <div class="absolute top-[75%] right-[48%] w-1 h-1 bg-white/80 rounded-full animate-particle-slow" style="animation-delay: -5s;" />
        <div class="absolute top-[85%] right-[25%] w-1 h-1 bg-white rounded-full animate-particle-slow" style="animation-delay: -6s;" />
      </div>

      <!-- Content -->
      <div class="relative z-10 p-8 lg:p-16 xl:p-20">
        <div class="max-w-2xl">

          <!-- Heading -->
          <h1 class="text-4xl lg:text-5xl xl:text-6xl font-bold mb-8 leading-[1.05]">
            <span class="text-surface-900 dark:text-white">La communauté de</span>
            <br />
            <span class="bg-gradient-to-r from-primary-600 via-fuchsia-500 to-cyan-500 dark:from-primary-400 dark:via-fuchsia-400 dark:to-cyan-400 bg-clip-text text-transparent">passionné·e·s de musique</span>
          </h1>

          <!-- Description -->
          <p class="text-xl text-surface-600 dark:text-surface-400 mb-10 leading-relaxed">
              Partagez votre passion, découvrez des contenus et connectez-vous avec des milliers de passionné·e·s sur MusicAll.
          </p>

          <!-- Features as pill tags -->
            <div class="flex flex-wrap gap-3 mb-10">
              <router-link
                :to="{ name: 'app_search_musician' }"
                class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm"
              >
                <i class="pi pi-users mr-2 text-fuchsia-600 dark:text-fuchsia-400"/>Trouver un musicien ou groupe
              </router-link>
              <router-link
                :to="{ name: 'app_publications' }"
                class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm"
              >
                <i class="pi pi-book mr-2 text-amber-600 dark:text-amber-400"/>Publications
              </router-link>
              <router-link
                :to="{ name: 'app_course' }"
                class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm"
              >
                <i class="pi pi-graduation-cap mr-2 text-emerald-600 dark:text-emerald-400"/>Cours
              </router-link>
              <router-link
                :to="{ name: 'app_publications_by_category', params: { slug: 'photos' } }"
                class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm"
              >
                <i class="pi pi-images mr-2 text-cyan-600 dark:text-cyan-400"/>Photos de live
              </router-link>
              <router-link
                :to="{ name: 'app_forum_index' }"
                class="px-4 py-2 bg-white/20 dark:bg-white/5 border border-surface-400 dark:border-white/10 rounded-full text-surface-700 dark:text-surface-300 text-sm hover:bg-white/30 dark:hover:bg-white/10 transition-colors cursor-pointer backdrop-blur-sm"
              >
                <i class="pi pi-comments mr-2 text-violet-600 dark:text-violet-400"/>Forum
              </router-link>
            </div>

          <!-- CTA Buttons -->
          <div class="flex flex-wrap gap-4">
            <router-link v-if="!userSecurityStore.isAuthenticated" :to="{ name: 'app_register' }">
              <Button
                label="S'inscrire gratuitement"
                icon="pi pi-arrow-right"
                iconPos="right"
                rounded
                size="large"
                class="!bg-gradient-to-r !from-[#6b97be] !to-[#4a7599] dark:!from-[#7ba7ce] dark:!to-[#5b87ae] hover:!from-[#5b87ae] hover:!to-[#3a6589] dark:hover:!from-[#8bb7de] dark:hover:!to-[#6b97be] !shadow-xl !shadow-[#5b87ae]/30 dark:!shadow-[#5b87ae]/40 hover:!shadow-[#5b87ae]/50 hover:!-translate-y-1 !transition-all !duration-300"
              />
            </router-link>
            <router-link :to="{ name: 'app_discover' }">
              <Button
                label="Découvrir"
                icon="pi pi-compass"
                rounded
                size="large"
                severity="secondary"
                outlined
                class="!bg-gradient-to-r !from-surface-700 !to-surface-800 dark:!bg-none dark:!bg-white/5 !backdrop-blur-sm !border-surface-600 dark:!border-white/20 !text-white dark:!text-surface-300 hover:!from-surface-600 hover:!to-surface-700 dark:hover:!bg-white/10 !transition-all !duration-300"
              />
            </router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- Latest Publications Section -->
    <section class="mb-12">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
          Dernières publications
        </h2>
        <div class="flex gap-2">
          <Button
            v-tooltip.bottom="'Ajouter une vidéo YouTube découverte'"
            label="Poster une découverte"
            icon="pi pi-plus"
            severity="info"
            size="small"
            class="hidden md:inline-flex"
            @click="handleOpenDiscoverModal"
          />
          <router-link :to="{ name: 'app_publications' }" aria-label="Voir toutes les publications">
            <Button label="Voir plus" icon="pi pi-arrow-right" iconPos="right" severity="secondary" text size="small" />
          </router-link>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <template v-if="isLoadingPublications">
          <PublicationListItemSkeleton v-for="i in 4" :key="i" />
        </template>
        <PublicationListItem
          v-for="publication in publicationsStore.lastPublications"
          :key="publication.id"
          :to-route="{ name: publication.sub_category.is_course ? 'app_course_show' : 'app_publication_show', params: { slug: publication.slug } }"
          :cover="publication.cover"
          :title="publication.title"
          :description="publication.description"
          :category="publication.sub_category"
          :author="publication.author"
          :date="publication.publication_datetime"
          :slug="publication.slug"
          :upvotes="publication.upvotes ?? 0"
          :downvotes="publication.downvotes ?? 0"
          :user-vote="publication.user_vote ?? null"
        />
      </div>
    </section>

    <!-- Latest Announces Section -->
    <section class="mb-12">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
          Dernières annonces
        </h2>
        <div class="flex gap-2">
          <Button
            label="Poster une annonce"
            icon="pi pi-plus"
            severity="info"
            size="small"
            class="hidden md:inline-flex"
            @click="handleOpenAnnounceModal"
          />
          <router-link :to="{ name: 'app_search_musician' }" aria-label="Voir toutes les annonces">
            <Button label="Voir plus" icon="pi pi-arrow-right" iconPos="right" severity="secondary" text size="small" />
          </router-link>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <template v-if="isLoadingAnnounces">
          <AnnounceCardSkeleton v-for="i in 6" :key="i" />
        </template>
        <Card v-for="announce in musicianAnnounceStore.lastAnnounces" :key="announce.id" class="transition-all duration-300 hover:shadow-lg hover:-translate-y-1">
          <template #content>
            <div class="flex gap-3">
              <router-link v-if="!announce.author.deletion_datetime" :to="getProfileRoute(announce)" class="shrink-0 hover:opacity-80 transition-opacity" :aria-label="`Voir le profil de ${getAuthorName(announce.author)}`">
                <Avatar
                  v-if="announce.author.profile_picture_url"
                  :image="announce.author.profile_picture_url"
                  :pt="{ image: { alt: `Photo de ${getAuthorName(announce.author)}` } }"
                  shape="circle"
                  role="img"
                  :aria-label="`Photo de ${getAuthorName(announce.author)}`"
                />
                <Avatar
                  v-else
                  :label="getAuthorName(announce.author).charAt(0).toUpperCase()"
                  :style="getAvatarStyle(getAuthorName(announce.author))"
                  shape="circle"
                  role="img"
                  :aria-label="`Avatar de ${getAuthorName(announce.author)}`"
                />
              </router-link>
              <div v-else class="shrink-0">
                <Avatar
                  :label="getAuthorName(announce.author).charAt(0).toUpperCase()"
                  :style="getAvatarStyle(getAuthorName(announce.author))"
                  shape="circle"
                  role="img"
                  :aria-label="`Avatar de ${getAuthorName(announce.author)}`"
                />
              </div>
              <div class="flex-1">
                <template v-if="isTypeBand(announce.type)">
                  <router-link v-if="!announce.author.deletion_datetime" :to="getProfileRoute(announce)" class="font-semibold hover:text-primary transition-colors">{{ getAuthorName(announce.author) }}</router-link>
                  <span v-else class="font-semibold text-surface-500">{{ getAuthorName(announce.author) }}</span> est un
                  <strong>{{ announce.instrument.musician_name.toLocaleLowerCase() }}</strong>
                  et cherche un groupe jouant du
                  <strong>{{ formatStyles(announce.styles).visible }}</strong>
                  <span
                    v-if="hasMoreStyles(announce.styles)"
                    v-tooltip.top="formatStyles(announce.styles).all"
                    class="text-primary cursor-help"
                  >
                    +{{ formatStyles(announce.styles).remaining }}
                  </span>
                  dans les alentours de {{ announce.location_name }}
                </template>
                <template v-if="isTypeMusician(announce.type)">
                  <router-link v-if="!announce.author.deletion_datetime" :to="getProfileRoute(announce)" class="font-semibold hover:text-primary transition-colors">{{ getAuthorName(announce.author) }}</router-link>
                  <span v-else class="font-semibold text-surface-500">{{ getAuthorName(announce.author) }}</span> cherche pour son groupe un
                  <strong>{{ announce.instrument.musician_name.toLocaleLowerCase() }}</strong>
                  jouant du
                  <strong>{{ formatStyles(announce.styles).visible }}</strong>
                  <span
                    v-if="hasMoreStyles(announce.styles)"
                    v-tooltip.top="formatStyles(announce.styles).all"
                    class="text-primary cursor-help"
                  >
                    +{{ formatStyles(announce.styles).remaining }}
                  </span>
                  dans les alentours de {{ announce.location_name }}
                </template>

                <div class="mt-2 text-xs text-surface-400 dark:text-surface-500">
                  <i class="pi pi-clock mr-1" />{{ relativeDate(announce.creation_datetime, { showHours: false }) }}
                </div>

                <div v-if="!isOwnAnnounce(announce) && !announce.author.deletion_datetime" class="mt-2 flex justify-end">
                  <Button
                    size="small"
                    icon="pi pi-envelope"
                    label="Contacter"
                    severity="secondary"
                    text
                    @click="handleContactAnnounce(announce.author)"
                  />
                </div>
              </div>
            </div>
          </template>
        </Card>
      </div>

      <div class="text-center mt-6">
        <router-link
          :to="{ name: 'app_search_musician' }"
          class="inline-flex items-center gap-2 text-primary hover:underline font-medium"
        >
          Voir toutes les annonces
          <i class="pi pi-arrow-right text-sm" />
        </router-link>
      </div>
    </section>

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
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { onMounted, onUnmounted, ref } from 'vue'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import AddDiscoverModal from '../../components/Publication/AddDiscoverModal.vue'
import AnnounceCardSkeleton from '../../components/Skeleton/AnnounceCardSkeleton.vue'
import PublicationListItemSkeleton from '../../components/Skeleton/PublicationListItemSkeleton.vue'
import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../../constants/types.js'
import { useMusicianAnnounceStore } from '../../store/announce/musician.js'
import { usePublicationsStore } from '../../store/publication/publications.js'
import { useVideoStore } from '../../store/publication/video.js'
import { useUserSecurityStore } from '../../store/user/security.js'
import { getAvatarStyle } from '../../utils/avatar.js'
import { formatStyles, hasMoreStyles } from '../../utils/styles.js'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import PublicationListItem from '../Publication/PublicationListItem.vue'
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

function getAuthorName(author) {
  return displayName(author)
}

function isTypeBand(type) {
  return type === TYPES_ANNOUNCE_BAND
}

function isTypeMusician(type) {
  return type === TYPES_ANNOUNCE_MUSICIAN
}

function isOwnAnnounce(announce) {
  return userSecurityStore.userProfile?.id === announce.author.id
}

function getProfileRoute(announce) {
  const route = {
    name: announce.author.has_musician_profile ? 'app_user_musician_profile' : 'app_user_public_profile',
    params: { username: announce.author.username }
  }
  // Add from query param for contextual back navigation
  route.query = { from: 'home' }
  return route
}

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
