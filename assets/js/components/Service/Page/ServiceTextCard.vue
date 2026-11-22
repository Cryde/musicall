<template>
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-3 flex items-center gap-2">
      <i class="pi pi-align-left text-surface-400" aria-hidden="true" />{{ title }}
    </h2>
    <div class="text-surface-700 dark:text-surface-300 whitespace-pre-line">{{ text }}</div>
  </section>
</template>

<script setup>
/** A service's own words on its page (#1410), « Comment je travaille », as its member wrote them. */
defineProps({
  title: { type: String, required: true },
  text: { type: String, required: true }
})
</script>
