<template>
  <iframe
    v-if="src"
    :src="src"
    :title="frameTitle"
    :allow="mediaEmbedAllow(media)"
    :allowfullscreen="media.platform === 'youtube'"
    :scrolling="media.platform === 'soundcloud' ? 'no' : undefined"
    class="w-full h-full border-0"
  />
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { mediaEmbedAllow, mediaEmbedUrl } from '../../utils/mediaEmbed.js'

/** One profile media in its platform's own player (YouTube, Spotify, SoundCloud). Sized by its parent. */
const props = defineProps({
  media: { type: Object, required: true },
  autoplay: { type: Boolean, default: false },
  compact: { type: Boolean, default: false }
})

const { t } = useI18n()

const FRAME_TITLE_KEYS = {
  youtube: 'profile.media.youtube_video',
  spotify: 'profile.media.spotify_player',
  soundcloud: 'profile.media.soundcloud_player'
}

const src = computed(() =>
  mediaEmbedUrl(props.media, { autoplay: props.autoplay, compact: props.compact })
)
const frameTitle = computed(
  () => props.media.title || t(FRAME_TITLE_KEYS[props.media.platform] ?? 'profile.media.player')
)
</script>
