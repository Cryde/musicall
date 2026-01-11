<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="currentColor"
  >
    <path
      d="M9 19c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm9-12v9c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2c.4 0 .7.1 1 .3V9l-6 1.5V19c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2c.4 0 .7.1 1 .3V7l8-2z"
    />
  </svg>
</template>

<script setup>
defineProps({
  size: {
    type: [Number, String],
    default: 16
  }
})
</script>
