<template>
  <div class="flex flex-col md:flex-row gap-5">
    <div class="basis-12/12 md:basis-8/12">
      <div class="flex justify-between">
        <h2 class="text-xl font-semibold leading-tight text-surface-900 dark:text-surface-0 mb-4 md:mb-0">
          Dernières publications
        </h2>
      </div>
      <div class="hidden md:flex md:flex-wrap justify-end-safe gap-4 mb-10">
        <Button
          v-tooltip.bottom="'Ajouter une vidéo YouTube découverte'"
          label="Poster une découverte"
          icon="pi pi-plus"
          severity="info"
          size="small"
          @click="handleOpenDiscoverModal"
        />
        <Button
          v-tooltip.bottom="'Ajouter une publication'"
          label="Poster une publication"
          icon="pi pi-plus"
          severity="info"
          size="small"
        />
      </div>

      <div class="self-stretch flex flex-col gap-8">
        <div class="grid grid-cols-1 xl:grid-cols-1 gap-3">
          <template v-if="publicationsStore.publications.length === 0 && !fetchedItems">
            <PublicationListItemSkeleton v-for="i in 12" :key="i" />
          </template>
          <PublicationListItem
            v-for="publication in publicationsStore.publications"
            :to-route="{name: 'app_publication_show', params: {slug: publication.slug}}"
            :key="publication.id"
            :cover="publication.cover"
            :title="publication.title"
            :description="publication.description"
            :category="publication.sub_category"
            :author="publication.author"
            :date="publication.publication_datetime"
          />
        </div>
      </div>
    </div>
    <div class="hidden md:block md:basis-4/12">
      <h2 class="text-xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
        Dernières annonces
      </h2>

      <div class="flex flex-wrap justify-end-safe gap-4 mb-10">
        <Button
          label="Poster une annonce"
          icon="pi pi-plus"
          severity="info"
          size="small"
          @click="handleOpenAnnounceModal"
        />
      </div>

      <div class="flex flex-col gap-2">
        <template v-if="isLoadingAnnounces">
          <AnnounceCardSkeleton v-for="i in 3" :key="i" />
        </template>
        <Card
          v-for="announce in musicianAnnounceStore.lastAnnounces"
          :key="announce.id"
        >
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
                  <strong>
                    {{ announce.instrument.musician_name.toLocaleLowerCase() }}
                  </strong> et cherche un groupe jouant du
                  <strong>
                    {{ announce.styles.map(style => style.name.toLocaleLowerCase()).join(', ') }}
                  </strong>
                  dans les alentours de {{ announce.location_name }}
                </template>
                <template v-if="isTypeMusician(announce.type)">
                  <span class="font-semibold">{{ announce.author.username }}</span> cherche pour son groupe un
                  <strong>
                    {{ announce.instrument.musician_name.toLocaleLowerCase() }}
                  </strong> jouant du
                  <strong>
                    {{ announce.styles.map(style => style.name.toLocaleLowerCase()).join(', ') }}
                  </strong> dans les alentours de {{ announce.location_name }}
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
    </div>

    <AddDiscoverModal @published="handleDiscoverPublished" />
    <SendMessageModal
      v-model:visible="showMessageModal"
      :selected-recipient="selectedRecipient"
    />
    <AuthRequiredModal
      v-model:visible="showAuthModal"
      :message="authModalMessage"
    />
    <AddAnnounceModal
      v-model:visible="showAnnounceModal"
      @created="handleAnnounceCreated"
    />
  </div>
</template>
<script setup>
import { useInfiniteScroll, useTitle } from '@vueuse/core'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
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
import PublicationListItem from '../Publication/PublicationListItem.vue'
import AddAnnounceModal from '../User/Announce/AddAnnounceModal.vue'

useTitle('MusicAll, le site de référence au service de la musique')

const publicationsStore = usePublicationsStore()
const musicianAnnounceStore = useMusicianAnnounceStore()
const videoStore = useVideoStore()
const userSecurityStore = useUserSecurityStore()

const currentPage = ref(1)
const fetchedItems = ref()
const showAuthModal = ref(false)
const showMessageModal = ref(false)
const showAnnounceModal = ref(false)
const selectedRecipient = ref(null)
const authModalMessage = ref('')
const isLoadingAnnounces = ref(true)

onMounted(async () => {
  // Load initial data
  await Promise.all([
    infiniteHandler(),
    (async () => {
      isLoadingAnnounces.value = true
      await musicianAnnounceStore.loadLastAnnounces()
      isLoadingAnnounces.value = false
    })()
  ])
})

const infiniteHandler = async () => {
  fetchedItems.value = await publicationsStore.loadPublications({
    page: currentPage.value
  })
  currentPage.value++
}

const canLoadMore = () => {
  return !fetchedItems.value || !!fetchedItems.value.length
}

const { reset } = useInfiniteScroll(document, infiniteHandler, {
  distance: 1000,
  canLoadMore
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
  currentPage.value = 1
  fetchedItems.value = undefined
  publicationsStore.resetPublications()
  await nextTick()
  reset()
}

onUnmounted(() => {
  fetchedItems.value = undefined
  publicationsStore.clear()
  musicianAnnounceStore.clear()
})
</script>
