<template>
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-2 flex items-center gap-2">
      <i class="pi pi-map-marker text-surface-400" aria-hidden="true" />{{ title }}
    </h2>
    <ul class="divide-y divide-surface-100 dark:divide-surface-800">
      <li v-for="(place, index) in places" :key="index" class="flex items-start gap-3 py-2.5">
        <span class="w-9 h-9 shrink-0 rounded-lg bg-primary-50 dark:bg-primary-400/15 text-primary-700 dark:text-primary-300 flex items-center justify-center">
          <i :class="place.icon" aria-hidden="true" />
        </span>
        <div class="min-w-0">
          <p class="font-medium text-surface-900 dark:text-surface-0">{{ place.label }}</p>
          <p v-if="place.detail" class="text-sm text-surface-500 dark:text-surface-400">
            {{ place.detail }}
          </p>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup>
/** « Où »: where a service happens, one line per place. */
defineProps({
  title: { type: String, required: true },
  /** `{ icon, label, detail }`, the detail already worded by the kind. */
  places: { type: Array, required: true }
})
</script>
