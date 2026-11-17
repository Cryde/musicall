<template>
  <dl v-if="items.length" class="flex flex-wrap gap-x-7 gap-y-3 mt-5">
    <div v-for="item in items" :key="item.key" class="flex flex-col-reverse">
      <dt class="text-xs text-surface-500 dark:text-surface-400">{{ item.label }}</dt>
      <dd class="text-xl font-bold text-surface-900 dark:text-surface-0">{{ item.value }}</dd>
    </div>
  </dl>
</template>

<script setup>
/** « 25 € les 30 minutes », « 5 jours délai moyen »: the few numbers a header leads with. */
defineProps({
  /** `{ key, value, label }`. */
  items: { type: Array, required: true }
})
</script>
