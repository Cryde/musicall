<template>
  <section class="py-2" :aria-labelledby="title ? titleId : undefined">
    <h2 v-if="title" :id="titleId" class="m-0 px-3 pt-1.5 pb-1 text-sm font-semibold text-surface-500 dark:text-surface-400">
      {{ title }}
    </h2>
    <ul class="m-0 list-none p-0">
      <slot />
    </ul>
  </section>
</template>

<script setup>
import { useId } from 'vue'

/** One group of lines in the phone menu, under an optional heading. */
defineProps({
  title: { type: String, default: null }
})

const titleId = useId()
</script>
