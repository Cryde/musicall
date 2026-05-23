<template>
  <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-6 flex flex-col h-full">
    <div class="flex items-center justify-between mb-4 gap-2">
      <h3 class="font-semibold text-base lg:text-lg flex items-center gap-2 text-surface-900 dark:text-surface-0">
        <i v-if="icon" :class="['text-xl text-primary', icon]" aria-hidden="true" />
        {{ title }}
      </h3>
      <slot name="header-action" />
    </div>
    <div class="flex-1 flex flex-col">
      <div v-if="isLoading" class="flex flex-col gap-2">
        <Skeleton v-for="i in skeletonRows" :key="i" height="2rem" />
      </div>
      <div v-else-if="error" class="text-sm text-red-500 flex items-center gap-2 py-4">
        <i class="pi pi-exclamation-circle" aria-hidden="true" />
        {{ error }}
      </div>
      <div v-else-if="isEmpty" class="flex-1 flex items-center justify-center text-sm text-surface-500 dark:text-surface-400 text-center py-6">
        {{ emptyMessage }}
      </div>
      <div v-else class="flex-1">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'

defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: null },
  isLoading: { type: Boolean, default: false },
  error: { type: String, default: null },
  isEmpty: { type: Boolean, default: false },
  emptyMessage: { type: String, default: 'Aucune donnée à afficher pour le moment.' },
  skeletonRows: { type: Number, default: 3 }
})
</script>
