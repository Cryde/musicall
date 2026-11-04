<template>
  <DatePicker
    :model-value="dueDateRange"
    selectionMode="range"
    :placeholder="t('band_space_tasks.field.due')"
    :aria-label="t('band_space_tasks.field.due')"
    size="small"
    showIcon
    showButtonBar
    :manualInput="false"
    :inputClass="inputClass"
    @update:model-value="emitDateRange"
  />
</template>

<script setup>
import DatePicker from 'primevue/datepicker'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * The due date range filter, the desktop bar's and the phone's « Filtres » sheet's. The picker shows
 * the dates as the page's locale writes them; the filter keeps `Y-m-d`, which the API reads.
 */
const props = defineProps({
  from: { type: String, default: null },
  to: { type: String, default: null },
  inputClass: { type: String, default: 'w-56 text-xs' }
})

const emit = defineEmits(['update-filter'])

const { t } = useI18n()

const dueDateRange = computed(() => {
  const from = props.from ? new Date(props.from) : null
  const to = props.to ? new Date(props.to) : null
  if (!from && !to) return null
  return [from, to]
})

function formatDate(date) {
  if (!date) return null
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function emitDateRange(value) {
  const [from, to] = Array.isArray(value) ? value : [null, null]
  emit('update-filter', 'dueDateFrom', formatDate(from))
  emit('update-filter', 'dueDateTo', formatDate(to))
}
</script>
