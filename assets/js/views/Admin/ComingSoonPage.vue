<template>
  <div class="flex flex-col gap-6">
    <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-100">{{ title }}</h1>

    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-12 flex flex-col items-center justify-center text-center gap-4">
      <div
        class="w-20 h-20 rounded-2xl flex items-center justify-center bg-surface-100 dark:bg-surface-800"
      >
        <i :class="['pi', icon, 'text-4xl text-surface-400']" />
      </div>
      <h2 class="text-xl font-semibold text-surface-700 dark:text-surface-200">Arrive bientôt</h2>
      <p v-if="description" class="text-surface-500 dark:text-surface-400 max-w-md">
        {{ description }}
      </p>
      <p v-else class="text-surface-500 dark:text-surface-400 max-w-md">
        Cette section est en cours de préparation. Repassez plus tard.
      </p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: 'pi-wrench' },
  description: { type: String, default: null }
})
</script>
