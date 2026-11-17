<template>
  <Avatar
    v-if="page.profile_picture_url"
    :image="page.profile_picture_url"
    :pt="{ image: { alt: '' } }"
    shape="circle"
    :class="[size, 'shrink-0']"
  />
  <Avatar
    v-else
    :label="page.display_name.charAt(0).toUpperCase()"
    :style="getAvatarStyle(page.username)"
    shape="circle"
    :class="[size, 'shrink-0']"
  />
</template>

<script setup>
import Avatar from 'primevue/avatar'
import { getAvatarStyle } from '../../../utils/avatar.js'

/** The member a service page belongs to: their picture, or their initial on their colour. */
defineProps({
  /** Any page with `profile_picture_url`, `display_name` and `username`. */
  page: { type: Object, required: true },
  /** Tailwind sizes, with `!` to beat PrimeVue's own. */
  size: { type: String, required: true }
})
</script>
