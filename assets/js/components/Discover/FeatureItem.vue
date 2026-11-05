<template>
  <!-- A tight list line on a phone; the icon gets its circle from md. The 3px drops the 13px icon
       onto the middle of the first 15px line, so it stays put when the line wraps. -->
  <div class="flex items-start gap-2.5 md:items-center md:gap-3">
    <span class="inline-flex items-center justify-center shrink-0 mt-[3px] md:mt-0 md:w-8 md:h-8 md:rounded-full md:border md:bg-primary-50 md:dark:bg-primary-400/30 md:border-primary-100 md:dark:border-primary-400/40">
      <i :class="[icon, 'text-[13px] md:text-xl leading-normal text-primary-700 dark:text-primary-300 md:text-primary-800']" aria-hidden="true" />
    </span>
    <p class="text-[15px] leading-[1.45] md:leading-6 md:text-lg text-surface-700 dark:text-surface-300 md:text-surface-600 md:dark:text-surface-400">
      <slot />
    </p>
  </div>
</template>

<script setup>
defineProps({
  icon: { type: String, default: 'pi pi-check' }
})
</script>
