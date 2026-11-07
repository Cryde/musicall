<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="media?.title || t('profile.media.player')"
    :style="{ width: '90vw', maxWidth: '900px' }"
  >
    <div v-if="media" class="aspect-video bg-black rounded-lg overflow-hidden">
      <MediaEmbedFrame :media="media" autoplay />
    </div>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import { useI18n } from 'vue-i18n'
import MediaEmbedFrame from './MediaEmbedFrame.vue'

/** Plays one profile media (YouTube, Spotify, SoundCloud) in a dialog. */
defineProps({
  media: { type: Object, default: null }
})

const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()
</script>
