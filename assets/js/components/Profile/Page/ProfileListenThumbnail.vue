<template>
  <img
    v-if="track.thumbnail_url"
    :src="track.thumbnail_url"
    alt=""
    class="rounded-lg object-cover shrink-0"
    :class="box"
  />
  <span
    v-else
    class="rounded-lg shrink-0 flex items-center justify-center bg-surface-100 dark:bg-surface-800 text-surface-500"
    :class="box"
    aria-hidden="true"
  >
    <i :class="mediaPlatformIcon(track.platform)" />
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { mediaPlatformIcon } from '../../../utils/mediaEmbed.js'

/** A track's stored thumbnail only: a YouTube still would be a request to YouTube before any click (#1317). */
const props = defineProps({
  track: { type: Object, required: true },
  large: { type: Boolean, default: false }
})

const box = computed(() => (props.large ? 'w-14 h-14' : 'w-10 h-10'))
</script>
