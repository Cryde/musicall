<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="media?.title || t('profile.media.player')"
    :style="{ width: '90vw', maxWidth: '900px' }"
  >
    <div v-if="media" class="aspect-video bg-black rounded-lg overflow-hidden">
      <iframe
        v-if="media.platform === 'youtube'"
        :src="`https://www.youtube.com/embed/${media.embed_id}?autoplay=1`"
        :title="media.title || t('profile.media.youtube_video')"
        class="w-full h-full"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen
      />
      <iframe
        v-else-if="media.platform === 'spotify'"
        :src="`https://open.spotify.com/embed/${media.embed_id}`"
        :title="media.title || t('profile.media.spotify_player')"
        class="w-full h-full"
        frameborder="0"
        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
        loading="lazy"
      />
      <iframe
        v-else-if="media.platform === 'soundcloud'"
        :src="`https://w.soundcloud.com/player/?url=https://soundcloud.com/${media.embed_id}&color=%23ff5500&auto_play=true&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=true`"
        :title="media.title || t('profile.media.soundcloud_player')"
        class="w-full h-full"
        frameborder="0"
        allow="autoplay"
        scrolling="no"
      />
    </div>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import { useI18n } from 'vue-i18n'

/** Plays one profile media (YouTube, Spotify, SoundCloud) in a dialog. */
defineProps({
  media: { type: Object, default: null }
})

const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()
</script>
