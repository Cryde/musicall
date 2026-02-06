<template>
  <div class="flex items-center gap-2">
    <Button
      :label="buttonLabel"
      icon="pi pi-calendar"
      severity="secondary"
      outlined
      size="small"
      @click="toggle"
    />
    <Popover ref="popoverRef">
      <div class="flex flex-col sm:flex-row">
        <!-- Presets Panel -->
        <div class="flex flex-col gap-1 p-3 sm:border-r border-b sm:border-b-0 border-surface-200 dark:border-surface-700 min-w-44">
          <span class="text-xs font-semibold text-surface-400 uppercase tracking-wider mb-1 px-2">Période</span>
          <button
            v-for="preset in presets"
            :key="preset.key"
            type="button"
            :class="[
              'text-left px-3 py-1.5 rounded-lg text-sm transition-colors',
              activePreset === preset.key
                ? 'bg-primary text-primary-contrast'
                : 'hover:bg-surface-100 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
            ]"
            @click="applyPreset(preset)"
          >
            {{ preset.label }}
          </button>
        </div>
        <!-- Calendar -->
        <div class="p-3">
          <DatePicker
            v-model="dateRange"
            selectionMode="range"
            :manualInput="false"
            :maxDate="today"
            :numberOfMonths="1"
            inline
            @update:modelValue="handleRangeChange"
          />
        </div>
      </div>
    </Popover>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Popover from 'primevue/popover'
import { format, subDays, subYears, startOfDay, startOfWeek, startOfMonth, startOfYear } from 'date-fns'
import { fr } from 'date-fns/locale'

const props = defineProps({
  from: { type: Date, required: true },
  to: { type: Date, required: true }
})

const emit = defineEmits(['apply'])

const popoverRef = ref()
const today = startOfDay(new Date())
const dateRange = ref([new Date(props.from), new Date(props.to)])
const activePreset = ref(null)

const presets = [
  { key: 'today', label: "Aujourd'hui", from: () => today, to: () => today },
  { key: '7d', label: '7 derniers jours', from: () => subDays(today, 6), to: () => today },
  { key: '14d', label: '14 derniers jours', from: () => subDays(today, 13), to: () => today },
  { key: '30d', label: '30 derniers jours', from: () => subDays(today, 29), to: () => today },
  { key: 'this_week', label: 'Cette semaine', from: () => startOfWeek(today, { locale: fr }), to: () => today },
  { key: 'this_month', label: 'Ce mois', from: () => startOfMonth(today), to: () => today },
  { key: 'this_year', label: 'Cette année', from: () => startOfYear(today), to: () => today },
  { key: '1y', label: 'Dernière année', from: () => subYears(today, 1), to: () => today },
  { key: '2y', label: '2 dernières années', from: () => subYears(today, 2), to: () => today },
  { key: 'all', label: 'Depuis le début', from: () => new Date(2008, 3, 30), to: () => today },
]

const buttonLabel = computed(() => {
  return format(props.from, 'd MMM yyyy', { locale: fr }) + ' – ' + format(props.to, 'd MMM yyyy', { locale: fr })
})

function toggle(event) {
  dateRange.value = [new Date(props.from), new Date(props.to)]
  activePreset.value = null
  popoverRef.value.toggle(event)
}

function applyAndClose(from, to) {
  emit('apply', { from, to })
  popoverRef.value.hide()
}

function applyPreset(preset) {
  activePreset.value = preset.key
  const from = preset.from()
  const to = preset.to()
  dateRange.value = [from, to]
  applyAndClose(from, to)
}

function handleRangeChange(value) {
  if (Array.isArray(value) && value.length === 2 && value[0] && value[1]) {
    activePreset.value = null
    applyAndClose(value[0], value[1])
  }
}
</script>
