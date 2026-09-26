<template>
  <span class="relative inline-flex shrink-0 self-start">
    <Avatar :username="username" :picture-url="pictureUrl" size="md" />
    <!-- The ring cuts the dot out of the picture, so it reads on a photo as well as on the initial. -->
    <span
      v-if="online"
      class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-surface-0 dark:ring-surface-900"
      aria-hidden="true"
    />
  </span>
</template>

<script setup>
import Avatar from '../../User/Avatar.vue'

/** A member's picture, marked when they have the chat open (#1040). */
defineProps({
  username: { type: String, required: true },
  pictureUrl: { type: String, default: null },
  online: { type: Boolean, default: false }
})
</script>
