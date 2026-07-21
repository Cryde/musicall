<template>
  <div v-if="isLoading && entries.length === 0" class="flex justify-center py-16">
    <ProgressSpinner style="width: 44px; height: 44px" />
  </div>
  <div v-else class="flex flex-col gap-6">
    <!-- Doughnuts + per-category legend -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <section
        v-for="chart in charts"
        :key="chart.key"
        class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4"
      >
        <div class="flex items-center justify-between mb-3 gap-2">
          <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-300">{{ chart.title }}</h3>
          <span class="text-sm font-semibold tabular-nums">{{ formatAmount(chart.total) }}</span>
        </div>

        <template v-if="chart.rows.length > 0">
          <Chart
            type="doughnut"
            :data="chart.data"
            :options="chartOptions"
            :canvas-props="{ role: 'img', 'aria-label': chart.title }"
            class="h-64"
          />
          <ul class="mt-4 space-y-1.5">
            <li v-for="row in chart.rows" :key="row.id" class="flex items-center gap-2 text-sm">
              <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: row.color }" />
              <span class="flex-1 min-w-0 truncate text-surface-700 dark:text-surface-300">{{ row.name }}</span>
              <span class="tabular-nums text-surface-500 dark:text-surface-400 shrink-0">{{ percent(row.value, chart.total) }}%</span>
              <span class="tabular-nums text-surface-600 dark:text-surface-300 shrink-0 w-24 text-right">{{ formatAmount(row.value) }}</span>
            </li>
          </ul>
        </template>
        <div v-else class="text-surface-400 dark:text-surface-500 italic text-center py-16">
          {{ chart.emptyLabel }}
        </div>
      </section>
    </div>

    <!-- Top 10 biggest entries -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <section
        v-for="top in topLists"
        :key="top.key"
        class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4"
      >
        <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-300 mb-3">{{ top.title }}</h3>
        <ol v-if="top.rows.length > 0" class="space-y-2">
          <li v-for="(entry, index) in top.rows" :key="entry.id" class="flex items-center gap-3 text-sm">
            <span class="text-xs font-semibold text-surface-400 dark:text-surface-500 w-5 shrink-0 tabular-nums">{{ index + 1 }}</span>
            <div class="flex-1 min-w-0">
              <div class="truncate font-medium text-surface-700 dark:text-surface-200">{{ entry.label }}</div>
              <div class="text-xs text-surface-500 dark:text-surface-400 truncate">
                {{ entry.categoryName }}<template v-if="entry.categoryName && entry.dateLabel"> · </template>{{ entry.dateLabel }}
              </div>
            </div>
            <span class="tabular-nums font-semibold shrink-0">{{ formatAmount(entry.amount) }}</span>
          </li>
        </ol>
        <div v-else class="text-surface-400 dark:text-surface-500 italic text-center py-8">
          {{ top.emptyLabel }}
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import Chart from 'primevue/chart'
import ProgressSpinner from 'primevue/progressspinner'
import { computed } from 'vue'
import { effectiveAmount, formatAmount } from '../../../utils/currency.js'

const props = defineProps({
  // All-time entries (finance entry resources) and the category tree (poles + children).
  entries: { type: Array, required: true },
  categoryTree: { type: Array, required: true },
  isLoading: { type: Boolean, default: false }
})

const TOP_LIMIT = 10

const PALETTE = [
  '#3b82f6',
  '#f59e0b',
  '#10b981',
  '#8b5cf6',
  '#ef4444',
  '#06b6d4',
  '#ec4899',
  '#84cc16',
  '#f97316',
  '#14b8a6'
]

// Map every category id (pole or child) to its owning pole + a stable colour (by pole index),
// so a pole keeps the same colour in both charts.
const poleByCategory = computed(() => {
  const map = new Map()
  props.categoryTree.forEach((pole, index) => {
    const entry = { id: pole.id, name: pole.name, color: PALETTE[index % PALETTE.length] }
    map.set(pole.id, entry)
    for (const child of pole.children ?? []) {
      map.set(child.id, entry)
    }
  })
  return map
})

// Personal-scope entries are a member's own, not band finances - excluded, matching the accordion.
const bandEntries = computed(() => props.entries.filter((entry) => entry.scope !== 'personal'))

function buildChart(type, title, emptyLabel, key) {
  const totals = new Map()
  for (const entry of bandEntries.value) {
    if (entry.type !== type) continue
    const pole = poleByCategory.value.get(entry.category_id)
    if (!pole) continue
    const row = totals.get(pole.id) ?? { id: pole.id, name: pole.name, color: pole.color, value: 0 }
    row.value += effectiveAmount(entry)
    totals.set(pole.id, row)
  }
  // Iterate the tree order so colours/legend stay stable between charts.
  const rows = props.categoryTree
    .map((pole) => totals.get(pole.id))
    .filter((row) => row && row.value > 0)
  const total = rows.reduce((sum, row) => sum + row.value, 0)
  return {
    key,
    title,
    emptyLabel,
    rows,
    total,
    data: {
      labels: rows.map((row) => row.name),
      datasets: [
        {
          data: rows.map((row) => row.value),
          backgroundColor: rows.map((row) => row.color),
          borderWidth: 0
        }
      ]
    }
  }
}

function buildTop(type, title, emptyLabel, key) {
  const rows = bandEntries.value
    .filter((entry) => entry.type === type)
    .map((entry) => ({
      id: entry.id,
      label: entry.label,
      amount: effectiveAmount(entry),
      categoryName: poleByCategory.value.get(entry.category_id)?.name ?? '',
      dateLabel: entry.date ? format(parseISO(entry.date), 'dd/MM/yyyy') : ''
    }))
    .filter((entry) => entry.amount > 0)
    .sort((a, b) => b.amount - a.amount)
    .slice(0, TOP_LIMIT)
  return { key, title, emptyLabel, rows }
}

const charts = computed(() => [
  buildChart('income', 'Revenus par catégorie', 'Aucun revenu enregistré', 'income'),
  buildChart('expense', 'Dépenses par catégorie', 'Aucune dépense enregistrée', 'expense')
])

const topLists = computed(() => [
  buildTop('income', 'Top 10 des revenus', 'Aucun revenu enregistré', 'income'),
  buildTop('expense', 'Top 10 des dépenses', 'Aucune dépense enregistrée', 'expense')
])

function percent(value, total) {
  if (!total) return 0
  return Math.round((value / total) * 100)
}

// Legend is HTML (theme-aware); the canvas only draws the ring. chart.js tooltips default to a
// dark background with light text, readable over any slice.
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '62%',
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (ctx) => ` ${ctx.label} : ${formatAmount(ctx.parsed)}`
      }
    }
  }
}
</script>
