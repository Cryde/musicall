<template>
  <!-- Decorative: the member's name is always written beside it. -->
  <img
    v-if="member.profile_picture_url"
    :src="member.profile_picture_url"
    alt=""
    class="h-8 w-8 shrink-0 rounded-full object-cover"
  />
  <span
    v-else
    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-contrast"
    aria-hidden="true"
  >
    {{ member.display_name.charAt(0).toUpperCase() }}
  </span>
</template>

<script setup>
/** A member's picture in the settings roster, or their initial. */
defineProps({
  member: { type: Object, required: true }
})
</script>
