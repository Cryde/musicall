<template>
  <Avatar
    v-if="pictureUrl"
    :image="pictureUrl"
    :pt="{ image: { alt: '' } }"
    shape="circle"
    :size="size"
    class="shrink-0"
  />
  <Avatar
    v-else
    :label="initial"
    :style="getAvatarStyle(author.username)"
    shape="circle"
    :size="size"
    class="shrink-0"
    aria-hidden="true"
  />
</template>

<script setup>
import Avatar from 'primevue/avatar'
import { computed } from 'vue'
import { displayName } from '../../helper/user/displayName.js'
import { getAvatarStyle } from '../../utils/avatar.js'

/** An announce author's picture, or their initial on their colour; never the picture of a closed account. */
const props = defineProps({
  author: { type: Object, required: true },
  /** PrimeVue's size: `large` on a card. A row passes `normal` and sets the size through the avatar tokens. */
  size: { type: String, default: 'large' }
})

const pictureUrl = computed(() =>
  props.author.deletion_datetime ? null : props.author.profile_picture_url
)
const initial = computed(() => displayName(props.author).charAt(0).toUpperCase())
</script>
