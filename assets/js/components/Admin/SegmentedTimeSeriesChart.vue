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
        type="bar"
        :data="chartData"
        :options="chartOptions"
        class="h-56"
      />
      <div v-else class="text-surface-400 text-center py-8">
        {{ t('admin.time_series.no_data') }}
      </div>
    </template>
  </Card>
</template>

<script setup>
import { format, parseISO } from 'date-fns'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { dateFnsLocale } from '../../i18n/formatLocale.js'
import { segmentedSeries } from '../../utils/segmentedTimeSeries.js'

const props = defineProps({
  title: { type: String, required: true },
  icon: { type: String, required: true },
  seriesData: { type: Object, default: null },
  allDates: { type: Array, required: true },
  // [{ value, labelKey, color }], in the order they stack. A segment named by the data, a referring site,
  // has its `label` instead of a `labelKey`.
  segments: { type: Array, required: true }
})

const { t } = useI18n()

const chartData = computed(() => {
  if (!props.seriesData?.data?.data_points) return null

  const series = segmentedSeries(
    props.seriesData.data.data_points,
    props.allDates,
    props.segments.map((segment) => segment.value)
  )

  return {
    labels: props.allDates.map((d) => format(parseISO(d), 'd MMM', { locale: dateFnsLocale() })),
    datasets: props.segments.map((segment, index) => ({
      label: t('admin.time_series.segment_total', {
        segment: segment.label ?? t(segment.labelKey),
        total: series[index].total
      }),
      data: series[index].data,
      backgroundColor: segment.color,
      stack: 'registrations'
    }))
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true, position: 'bottom', labels: { boxWidth: 12 } }
  },
  scales: {
    x: {
      stacked: true,
      grid: { display: false },
      ticks: { maxRotation: 45, font: { size: 10 } }
    },
    y: {
      stacked: true,
      beginAtZero: true,
      ticks: { precision: 0 }
    }
  }
}
</script>
