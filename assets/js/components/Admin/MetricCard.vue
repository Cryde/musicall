<template>
  <Card class="overflow-hidden">
    <template #content>
      <div class="flex items-start justify-between mb-4">
        <div class="flex items-center gap-2">
          <i :class="['pi', icon, 'text-primary text-xl']" />
          <span class="font-semibold text-surface-700 dark:text-surface-300">{{ title }}</span>
        </div>
        <span v-if="trendPercent !== null" v-tooltip.top="'Évolution par rapport à la semaine précédente'">
          <TrendIndicator :value="trendPercent" />
        </span>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-sm text-surface-500">Aujourd'hui</span>
          <span class="text-2xl font-bold">{{ today }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-surface-500">7 jours</span>
          <span class="text-lg font-semibold text-surface-600 dark:text-surface-400">{{ week }}</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="text-sm text-surface-500">30 jours</span>
          <span class="text-lg font-semibold text-surface-600 dark:text-surface-400">{{ month }}</span>
        </div>
      </div>
    </template>
  </Card>
</template>

<script setup>
import Card from 'primevue/card'
import TrendIndicator from './TrendIndicator.vue'

defineProps({
  title: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  today: {
    type: Number,
    required: true
  },
  week: {
    type: Number,
    required: true
  },
  month: {
    type: Number,
    required: true
  },
  trendPercent: {
    type: Number,
    default: null
  }
})
</script>
