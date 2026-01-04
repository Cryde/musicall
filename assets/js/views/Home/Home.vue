<template>
  <div>
    <!-- Hero Section -->
    <section class="bg-surface-100 dark:bg-surface-900 p-8 lg:p-16 rounded-2xl mb-12">
      <div class="flex flex-wrap lg:flex-row flex-col-reverse gap-12 items-center">
        <div class="flex-1">
          <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-white mb-4 leading-tight">
            La communauté de
            <span class="text-primary underline">passionné·e·s de musique</span>
          </h1>
          <p class="text-lg text-surface-600 dark:text-gray-400 mb-8 leading-normal">
            Partagez votre passion, découvrez des contenus et connectez-vous avec des milliers de passionné·e·s sur MusicAll.
          </p>
          <ul class="list-none flex flex-col gap-4">
            <li class="flex items-center gap-3">
              <i class="pi pi-book text-primary text-xl" />
              <span class="text-surface-600 dark:text-gray-400 leading-normal">Articles, cours et actualités musicales</span>
            </li>
            <li class="flex items-center gap-3">
              <i class="pi pi-images text-primary text-xl" />
              <span class="text-surface-600 dark:text-gray-400 leading-normal">Galeries photos de concerts et événements</span>
            </li>
            <li class="flex items-center gap-3">
              <i class="pi pi-users text-primary text-xl" />
              <span class="text-surface-600 dark:text-gray-400 leading-normal">Annonces pour trouver musiciens et groupes</span>
            </li>
            <li class="flex items-center gap-3">
              <i class="pi pi-comments text-primary text-xl" />
              <span class="text-surface-600 dark:text-gray-400 leading-normal">Forum pour échanger avec la communauté</span>
            </li>
          </ul>
          <div class="flex flex-wrap gap-4 mt-10">
            <router-link :to="{ name: 'app_discover' }">
              <Button label="Découvrir" icon="pi pi-compass" rounded size="large" severity="secondary" />
            </router-link>
            <router-link v-if="!userSecurityStore.isAuthenticated" :to="{ name: 'app_register' }">
              <Button label="S'inscrire gratuitement" icon="pi pi-user-plus" rounded size="large" />
            </router-link>
          </div>
        </div>
        <div class="hidden lg:block flex-1 text-right">
          <div class="inline-flex justify-end gap-6">
            <div class="flex flex-col gap-6">
              <div class="w-24 h-24 lg:w-32 lg:h-32 rounded-2xl bg-primary/20 flex items-center justify-center">
                <i class="pi pi-headphones text-4xl lg:text-5xl text-primary" />
              </div>
              <div class="w-24 h-24 lg:w-32 lg:h-32 rounded-2xl bg-blue-500/20 flex items-center justify-center">
                <i class="pi pi-microphone text-4xl lg:text-5xl text-blue-500 dark:text-blue-400" />
              </div>
            </div>
            <div class="flex flex-col gap-6 mt-8">
              <div class="w-24 h-24 lg:w-32 lg:h-32 rounded-2xl bg-orange-500/20 flex items-center justify-center">
                <i class="pi pi-volume-up text-4xl lg:text-5xl text-orange-500 dark:text-orange-400" />
              </div>
              <div class="w-24 h-24 lg:w-32 lg:h-32 rounded-2xl bg-purple-500/20 flex items-center justify-center">
                <i class="pi pi-star text-4xl lg:text-5xl text-purple-500 dark:text-purple-400" />
              </div>
            </div>
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
          <router-link :to="{ name: 'app_publications' }">
            <Button label="Voir plus" icon="pi pi-arrow-right" iconPos="right" severity="secondary" text size="small" />
          </router-link>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <template v-if="isLoadingPublications">
          <PublicationListItemSkeleton v-for="i in 4" :key="i" />
        </template>
        <PublicationListItem
          v-for="publication in latestPublications"
          :key="publication.id"
          :to-route="{ name: 'app_publication_show', params: { slug: publication.slug } }"
          :cover="publication.cover"
          :title="publication.title"
          :description="publication.description"
          :category="publication.sub_category"
          :author="publication.author"
          :date="publication.publication_datetime"
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
          <router-link :to="{ name: 'app_search_musician' }">
            <Button label="Voir plus" icon="pi pi-arrow-right" iconPos="right" severity="secondary" text size="small" />
          </router-link>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <template v-if="isLoadingAnnounces">
          <AnnounceCardSkeleton v-for="i in 6" :key="i" />
        </template>
        <Card v-for="announce in musicianAnnounceStore.lastAnnounces" :key="announce.id">
          <template #content>
            <div class="flex gap-3">
              <Avatar
                v-if="announce.author.profile_picture_url"
                :image="announce.author.profile_picture_url"
                shape="circle"
                class="shrink-0"
              />
              <Avatar
                v-else
                :label="announce.author.username.charAt(0).toUpperCase()"
                shape="circle"
                class="shrink-0"
              />
              <div class="flex-1">
                <template v-if="isTypeBand(announce.type)">
                  <span class="font-semibold">{{ announce.author.username }}</span> est un
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
                  <span class="font-semibold">{{ announce.author.username }}</span> cherche pour son groupe un
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

                <div v-if="!isOwnAnnounce(announce)" class="mt-3 flex justify-end">
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
    </section>

    <!-- CTA Section -->
    <section v-if="!userSecurityStore.isAuthenticated" class="text-center py-12 bg-gradient-to-r from-primary-500 to-primary-600 rounded-2xl">
      <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">
        Rejoignez la communauté MusicAll
      </h2>
      <p class="text-primary-100 mb-6">
        Des milliers de passionné·e·s échangent déjà sur MusicAll
      </p>
      <router-link :to="{ name: 'app_register' }">
        <Button label="Créer mon compte gratuitement" icon="pi pi-user-plus" size="large" severity="contrast" />
      </router-link>
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
import { computed, onMounted, onUnmounted, ref } from 'vue'
import AuthRequiredModal from '../../components/Auth/AuthRequiredModal.vue'
import SendMessageModal from '../../components/Message/SendMessageModal.vue'
import AddDiscoverModal from '../../components/Publication/AddDiscoverModal.vue'
import AnnounceCardSkeleton from '../../components/Skeleton/AnnounceCardSkeleton.vue'
import PublicationListItemSkeleton from '../../components/Skeleton/PublicationListItemSkeleton.vue'
import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../../constants/types.js'
import { formatStyles, hasMoreStyles } from '../../utils/styles.js'
import { useMusicianAnnounceStore } from '../../store/announce/musician.js'
import { usePublicationsStore } from '../../store/publication/publications.js'
import { useVideoStore } from '../../store/publication/video.js'
import { useUserSecurityStore } from '../../store/user/security.js'
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

const latestPublications = computed(() => publicationsStore.publications.slice(0, 4))

onMounted(async () => {
  await Promise.all([
    (async () => {
      isLoadingPublications.value = true
      await publicationsStore.loadPublications({ page: 1 })
      isLoadingPublications.value = false
    })(),
    (async () => {
      isLoadingAnnounces.value = true
      await musicianAnnounceStore.loadLastAnnounces()
      isLoadingAnnounces.value = false
    })()
  ])
})

function isTypeBand(type) {
  return type === TYPES_ANNOUNCE_BAND
}

function isTypeMusician(type) {
  return type === TYPES_ANNOUNCE_MUSICIAN
}

function isOwnAnnounce(announce) {
  return userSecurityStore.userProfile?.id === announce.author.id
}

function handleOpenDiscoverModal() {
  if (!userSecurityStore.isAuthenticated) {
    authModalMessage.value = 'Si vous souhaitez partager une vidéo avec la communauté, vous devez vous connecter.'
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
  await publicationsStore.loadPublications({ page: 1 })
  isLoadingPublications.value = false
}

onUnmounted(() => {
  publicationsStore.clear()
  musicianAnnounceStore.clear()
})
</script>
