<template>
  <RouterLink
    v-if="!comingSoon"
    :to="{ name: route }"
    class="flex flex-col gap-3 p-5 rounded-2xl bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 hover:border-primary hover:shadow-md transition-all"
  >
    <div class="flex items-center justify-between">
      <div
        class="w-12 h-12 rounded-xl flex items-center justify-center"
        :style="{ backgroundColor: color + '20', color }"
      >
        <i :class="['pi', icon, 'text-2xl']" />
      </div>
      <Badge v-if="badgeCount > 0" :value="badgeCount" severity="warn" />
      <i v-else class="pi pi-arrow-right text-surface-400" />
    </div>
    <div class="flex flex-col gap-1">
      <span class="font-semibold text-surface-900 dark:text-surface-100">{{ label }}</span>
      <span v-if="description" class="text-sm text-surface-500 dark:text-surface-400">{{
        description
      }}</span>
    </div>
  </RouterLink>

  <div
    v-else
    class="flex flex-col gap-3 p-5 rounded-2xl bg-surface-50 dark:bg-surface-800 border border-dashed border-surface-300 dark:border-surface-600"
  >
    <div class="flex items-center justify-between">
      <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-surface-200 dark:bg-surface-700 text-surface-400">
        <i :class="['pi', icon, 'text-2xl']" />
      </div>
      <ComingSoonBadge />
    </div>
    <div class="flex flex-col gap-1">
      <span class="font-semibold text-surface-500 dark:text-surface-400">{{ label }}</span>
      <span v-if="description" class="text-sm text-surface-400 dark:text-surface-500">{{
        description
      }}</span>
    </div>
  </div>
</template>

<script setup>
import Badge from 'primevue/badge'
import ComingSoonBadge from './ComingSoonBadge.vue'

defineProps({
  label: { type: String, required: true },
  icon: { type: String, required: true },
  color: { type: String, default: '#6366f1' },
  route: { type: String, default: null },
  description: { type: String, default: null },
  badgeCount: { type: Number, default: 0 },
  comingSoon: { type: Boolean, default: false }
})
</script>
