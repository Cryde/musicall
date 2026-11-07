<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">
        {{ t('profile.media.title') }}
      </h2>
      <Button
        v-if="isOwnProfile && canAddMore"
        icon="pi pi-plus"
        :label="t('profile.media.add')"
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
        <div
          class="aspect-video relative cursor-pointer"
          role="button"
          tabindex="0"
          :aria-label="item.title ? t('profile.media.play', { title: item.title }) : t('profile.media.play_untitled')"
          @click="openMedia(item)"
          @keydown.enter="openMedia(item)"
          @keydown.space.prevent="openMedia(item)"
        >
          <img
            v-if="item.thumbnail_url"
            :src="item.thumbnail_url"
            :alt="item.title || t('profile.media.thumbnail_alt')"
            class="w-full h-full object-cover"
          />
          <div
            v-else
            class="w-full h-full flex items-center justify-center bg-surface-200 dark:bg-surface-700"
          >
            <i :class="mediaPlatformIcon(item.platform)" class="text-4xl text-surface-400" />
          </div>
          <!-- Play overlay -->
          <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
            <i class="pi pi-play-circle text-white text-4xl" />
          </div>
          <!-- Platform badge -->
          <div class="absolute top-2 right-2">
            <Tag :value="item.platform_label" :severity="mediaPlatformSeverity(item.platform)" size="small" />
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
            :aria-label="t('profile.media.delete_aria')"
            severity="danger"
            text
            rounded
            size="small"
            class="shrink-0"
            :loading="deletingId === item.id"
            @click.stop="handleDelete(item)"
          />
          <ReportButton
            v-else
            target-type="profile_media"
            :target-id="item.id"
            :author-username="ownerUsername"
            :aria-label="item.title ? t('profile.media.report', { title: item.title }) : t('profile.media.report_untitled')"
            icon-only
            class="shrink-0"
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
        {{ isOwnProfile ? t('profile.media.empty_own') : t('profile.media.empty') }}
      </p>
      <Button
        v-if="isOwnProfile"
        :label="t('profile.media.add_creation')"
        icon="pi pi-plus"
        @click="showAddModal = true"
      />
    </div>

    <!-- Media Player Modal -->
    <Dialog
      v-model:visible="showPlayerModal"
      modal
      :header="selectedMedia?.title || t('profile.media.player')"
      :style="{ width: '90vw', maxWidth: '900px' }"
    >
      <div v-if="selectedMedia" class="aspect-video bg-black rounded-lg overflow-hidden">
        <!-- YouTube embed -->
        <iframe
          v-if="selectedMedia.platform === 'youtube'"
          :src="`https://www.youtube.com/embed/${selectedMedia.embed_id}?autoplay=1`"
          :title="selectedMedia.title || t('profile.media.youtube_video')"
          class="w-full h-full"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowfullscreen
        />
        <!-- Spotify embed -->
        <iframe
          v-else-if="selectedMedia.platform === 'spotify'"
          :src="`https://open.spotify.com/embed/${selectedMedia.embed_id}`"
          :title="selectedMedia.title || t('profile.media.spotify_player')"
          class="w-full h-full"
          frameborder="0"
          allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
          loading="lazy"
        />
        <!-- SoundCloud embed -->
        <iframe
          v-else-if="selectedMedia.platform === 'soundcloud'"
          :src="`https://w.soundcloud.com/player/?url=https://soundcloud.com/${selectedMedia.embed_id}&color=%23ff5500&auto_play=true&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=true`"
          :title="selectedMedia.title || t('profile.media.soundcloud_player')"
          class="w-full h-full"
          frameborder="0"
          allow="autoplay"
          scrolling="no"
        />
      </div>
    </Dialog>

    <AddMediaDialog v-model:visible="showAddModal" :targets="['musician']" />

    <!-- Delete Confirmation -->
    <ConfirmDialog group="media-delete" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMusicianProfileMediaStore } from '../../../store/user/musicianProfileMedia.js'
import { mediaPlatformIcon, mediaPlatformSeverity } from '../../../utils/mediaEmbed.js'
import { MAX_PROFILE_MEDIA } from '../../../utils/profileOwner.js'
import AddMediaDialog from '../../Media/AddMediaDialog.vue'
import ReportButton from '../../Report/ReportButton.vue'

const props = defineProps({
  isOwnProfile: {
    type: Boolean,
    default: false
  },
  mediaItems: {
    type: Array,
    default: null
  },
  /** Whose media these are, so « Signaler » never shows to the owner while their profile loads. */
  ownerUsername: {
    type: String,
    default: null
  }
})

const confirm = useConfirm()
const { t } = useI18n()
const mediaStore = useMusicianProfileMediaStore()

const showPlayerModal = ref(false)
const showAddModal = ref(false)
const selectedMedia = ref(null)
const deletingId = ref(null)

const isLoading = computed(() => (props.isOwnProfile ? mediaStore.isLoading : false))

const media = computed(() => {
  if (props.mediaItems !== null) {
    return props.mediaItems
  }
  return mediaStore.media
})

const canAddMore = computed(() => media.value.length < MAX_PROFILE_MEDIA)

function openMedia(item) {
  selectedMedia.value = item
  showPlayerModal.value = true
}

function handleDelete(item) {
  confirm.require({
    group: 'media-delete',
    message: t('profile.media.delete_confirm_message'),
    header: t('profile.media.delete_confirm_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('profile.media.cancel'),
    acceptLabel: t('profile.media.delete'),
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
