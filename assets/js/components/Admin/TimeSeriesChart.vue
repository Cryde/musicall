<template>
  <Card>
    <template #title>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-base">
          <i :class="['pi', icon, 'text-primary']" />
          <span>{{ title }}</span>
        </div>
        <span v-if="seriesData?.data" class="text-2xl font-bold">
          {{ seriesData.data.total }}
        </span>
      </div>
    </template>
    <template #content>
      <div v-if="seriesData?.isLoading" class="flex justify-center py-8">
        <ProgressSpinner style="width: 40px; height: 40px" />
      </div>
      <Message v-else-if="seriesData?.error" severity="error" :closable="false">
        {{ seriesData.error }}
      </Message>
      <Chart
        v-else-if="chartData"
        type="line"
        :data="chartData"
        :options="chartOptions"
        class="h-48"
      />
      <div v-else class="text-surface-400 text-center py-8">
        Aucune donnée
      </div>
    </template>
  </Card>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { format, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'

const props = defineProps({
  title: { type: String, required: true },
  icon: { type: String, required: true },
  color: { type: String, default: '#6366f1' },
  seriesData: { type: Object, default: null },
  allDates: { type: Array, required: true }
})

const chartData = computed(() => {
  if (!props.seriesData?.data?.data_points) return null

  const countMap = {}
  for (const point of props.seriesData.data.data_points) {
    countMap[point.date_label] = point.count
  }

  const labels = props.allDates.map((d) => format(parseISO(d), 'd MMM', { locale: fr }))
  const data = props.allDates.map((d) => countMap[d] || 0)

  return {
    labels,
    datasets: [
      {
        label: props.title,
        data,
        backgroundColor: props.color + '20',
        borderColor: props.color,
        borderWidth: 2,
        fill: true,
        tension: 0.3,
        pointRadius: 2,
        pointHoverRadius: 5
      }
    ]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { maxRotation: 45, font: { size: 10 } }
    },
    y: {
      beginAtZero: true,
      ticks: { precision: 0 }
    }
  }
}
</script>
