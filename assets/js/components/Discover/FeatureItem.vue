<template>
  <div class="flex items-center gap-3">
    <span class="w-8 h-8 inline-flex items-center justify-center rounded-full shrink-0 border bg-primary-50 dark:bg-primary-400/30 border-primary-100 dark:border-primary-400/40">
      <i :class="[icon, 'text-xl leading-normal text-primary-800 dark:text-primary-300']" aria-hidden="true" />
    </span>
    <p class="leading-6 text-lg text-surface-600 dark:text-surface-400">
      <slot />
    </p>
  </div>
</template>

<script setup>
defineProps({
  icon: { type: String, default: 'pi pi-check' }
})
</script>
