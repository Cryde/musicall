<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">
        Mes créations
      </h2>
      <Button
        v-if="isOwnProfile && canAddMore"
        icon="pi pi-plus"
        label="Ajouter"
        size="small"
        @click="showAddModal = true"
      />
    </div>

    <!-- Loading state -->
    <div v-if="isLoading" class="flex justify-center py-8">
      <ProgressSpinner />
    </div>

    <!-- Grid layout -->
    <div v-else-if="media.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="item in media"
        :key="item.id"
        class="group relative bg-surface-100 dark:bg-surface-800 rounded-lg overflow-hidden"
      >
        <!-- Thumbnail -->
        <div class="aspect-video relative cursor-pointer" @click="openMedia(item)">
          <img
            v-if="item.thumbnail_url"
            :src="item.thumbnail_url"
            :alt="item.title || 'Média'"
            class="w-full h-full object-cover"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center bg-surface-200 dark:bg-surface-700"
          >
            <i :class="getPlatformIcon(item.platform)" class="text-4xl text-surface-400" />
          </div>
          <!-- Play overlay -->
          <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <i class="pi pi-play-circle text-white text-4xl" />
          </div>
          <!-- Platform badge -->
          <div class="absolute top-2 right-2">
            <Tag :value="item.platform_label" :severity="getPlatformSeverity(item.platform)" size="small" />
          </div>
        </div>
        <!-- Info -->
        <div class="p-3 flex items-start gap-2">
          <div class="flex-1 min-w-0">
            <h3 class="font-medium text-surface-900 dark:text-surface-0 text-sm line-clamp-2">
              {{ item.title || item.url }}
            </h3>
          </div>
          <Button
            v-if="isOwnProfile"
            icon="pi pi-trash"
            severity="danger"
            text
            rounded
            size="small"
            class="shrink-0"
            :loading="deletingId === item.id"
            @click.stop="handleDelete(item)"
          />
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-8">
      <div class="flex items-center justify-center w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 mx-auto mb-4">
        <i class="pi pi-video text-2xl text-surface-400" />
      </div>
      <p class="text-surface-500 dark:text-surface-400 mb-4">
        {{ isOwnProfile ? 'Vous n\'avez pas encore ajouté de création' : 'Aucune création à afficher' }}
      </p>
      <Button
        v-if="isOwnProfile"
        label="Ajouter une création"
        icon="pi pi-plus"
        @click="showAddModal = true"
      />
    </div>

    <!-- Media Player Modal -->
    <Dialog
      v-model:visible="showPlayerModal"
      modal
      :header="selectedMedia?.title || 'Lecteur'"
      :style="{ width: '90vw', maxWidth: '900px' }"
    >
      <div v-if="selectedMedia" class="aspect-video bg-black rounded-lg overflow-hidden">
        <!-- YouTube embed -->
        <iframe
          v-if="selectedMedia.platform === 'youtube'"
          :src="`https://www.youtube.com/embed/${selectedMedia.embed_id}?autoplay=1`"
          class="w-full h-full"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
        />
        <!-- Spotify embed -->
        <iframe
          v-else-if="selectedMedia.platform === 'spotify'"
          :src="`https://open.spotify.com/embed/${selectedMedia.embed_id}`"
          class="w-full h-full"
          frameborder="0"
          allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
          loading="lazy"
        />
        <!-- SoundCloud embed -->
        <iframe
          v-else-if="selectedMedia.platform === 'soundcloud'"
          :src="`https://w.soundcloud.com/player/?url=https://soundcloud.com/${selectedMedia.embed_id}&color=%23ff5500&auto_play=true&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=true`"
          class="w-full h-full"
          frameborder="0"
          allow="autoplay"
          scrolling="no"
        />
      </div>
    </Dialog>

    <!-- Add Media Modal -->
    <Dialog
      v-model:visible="showAddModal"
      modal
      header="Ajouter une création"
      :style="{ width: '500px' }"
    >
      <div class="flex flex-col gap-4">
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
            Lien vers votre création
          </label>
          <InputText
            v-model="newMediaUrl"
            placeholder="https://youtube.com/watch?v=... ou soundcloud.com/..."
            class="w-full"
            :invalid="!!urlError"
          />
          <p v-if="urlError" class="text-xs text-red-500 mt-1">
            {{ urlError }}
          </p>
          <p v-else class="text-xs text-surface-500 dark:text-surface-400 mt-1">
            Plateformes supportées : YouTube, SoundCloud, Spotify
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
            Titre (optionnel)
          </label>
          <InputText
            v-model="newMediaTitle"
            placeholder="Nom de votre création"
            class="w-full"
          />
        </div>

        <!-- Platform detection preview -->
        <div v-if="detectedPlatform" class="p-4 bg-surface-100 dark:bg-surface-800 rounded-lg">
          <div class="flex items-center gap-3">
            <Tag :value="detectedPlatform" :severity="getPlatformSeverity(detectedPlatform.toLowerCase())" />
            <span class="text-sm text-surface-600 dark:text-surface-300">
              Plateforme détectée
            </span>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="closeAddModal" />
        <Button
          label="Ajouter"
          icon="pi pi-plus"
          :disabled="!newMediaUrl"
          :loading="isAdding"
          @click="handleAdd"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation -->
    <ConfirmDialog group="media-delete" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { computed, onMounted, ref, watch } from 'vue'
import { useMusicianProfileMediaStore } from '../../../store/user/musicianProfileMedia.js'

const MAX_MEDIA = 6

const props = defineProps({
  isOwnProfile: {
    type: Boolean,
    default: false
  },
  mediaItems: {
    type: Array,
    default: null
  }
})

const confirm = useConfirm()
const mediaStore = useMusicianProfileMediaStore()

const showPlayerModal = ref(false)
const showAddModal = ref(false)
const selectedMedia = ref(null)
const newMediaUrl = ref('')
const newMediaTitle = ref('')
const urlError = ref('')
const deletingId = ref(null)

const isLoading = computed(() => props.isOwnProfile ? mediaStore.isLoading : false)
const isAdding = computed(() => mediaStore.isAdding)

const media = computed(() => {
  if (props.mediaItems !== null) {
    return props.mediaItems
  }
  return mediaStore.media
})

const canAddMore = computed(() => media.value.length < MAX_MEDIA)

const detectedPlatform = computed(() => {
  if (!newMediaUrl.value) return null
  const url = newMediaUrl.value.toLowerCase()
  if (url.includes('youtube') || url.includes('youtu.be')) return 'YouTube'
  if (url.includes('soundcloud')) return 'SoundCloud'
  if (url.includes('spotify')) return 'Spotify'
  return null
})

watch(newMediaUrl, () => {
  urlError.value = ''
})

function getPlatformSeverity(platform) {
  switch (platform) {
    case 'youtube': return 'danger'
    case 'soundcloud': return 'warn'
    case 'spotify': return 'success'
    default: return 'info'
  }
}

function getPlatformIcon(platform) {
  switch (platform) {
    case 'youtube': return 'pi pi-youtube'
    case 'soundcloud': return 'pi pi-cloud'
    case 'spotify': return 'pi pi-spotify'
    default: return 'pi pi-video'
  }
}

function openMedia(item) {
  selectedMedia.value = item
  showPlayerModal.value = true
}

async function handleAdd() {
  if (!newMediaUrl.value) return

  try {
    await mediaStore.addMedia(newMediaUrl.value, newMediaTitle.value || null)
    closeAddModal()
  } catch (error) {
    if (error.isValidationError) {
      urlError.value = error.message || 'URL invalide'
    } else {
      urlError.value = error.message || 'Une erreur est survenue'
    }
  }
}

function closeAddModal() {
  showAddModal.value = false
  newMediaUrl.value = ''
  newMediaTitle.value = ''
  urlError.value = ''
}

function handleDelete(item) {
  confirm.require({
    group: 'media-delete',
    message: 'Êtes-vous sûr de vouloir supprimer cette création ?',
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      deletingId.value = item.id
      try {
        await mediaStore.deleteMedia(item.id)
      } finally {
        deletingId.value = null
      }
    }
  })
}

onMounted(() => {
  if (props.isOwnProfile && props.mediaItems === null) {
    mediaStore.loadMedia()
  }
})
</script>
