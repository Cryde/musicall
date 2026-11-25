<template>
  <!-- Its gap comes from where it is used: each section spaces its fields its own way. -->
  <section class="flex flex-col">
    <h3 class="flex items-center gap-2 pb-2 border-b border-surface-200 dark:border-surface-700 text-base font-semibold text-surface-900 dark:text-surface-0">
      <i :class="icon" class="text-primary-500" aria-hidden="true" />{{ title }}
    </h3>
    <slot />
  </section>
</template>

<script setup>
/** One section of a service's form (#1410, #1411), under its titled rule. */
defineProps({
  icon: { type: String, required: true },
  title: { type: String, required: true }
})
</script>
