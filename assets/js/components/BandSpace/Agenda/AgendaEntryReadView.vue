<template>
  <div class="flex flex-col gap-4">
    <div>
      <span
        class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
        :class="agendaSourceFor(item.source).badgeClass"
      >
        {{ agendaSourceLabel(item.source) }}
      </span>
      <h2 class="m-0 mt-2 text-xl font-semibold text-surface-900 [overflow-wrap:anywhere] dark:text-surface-0">
        {{ item.title }}
      </h2>
    </div>

    <dl class="-mx-5 m-0 divide-y divide-surface-200 border-y border-surface-200 dark:divide-surface-700 dark:border-surface-700">
      <div v-for="row in rows" :key="row.key" class="flex items-start gap-3.5 px-5 py-3.5 text-sm">
        <i class="pi mt-0.5 w-4 shrink-0 text-center text-surface-500 dark:text-surface-400" :class="row.icon" aria-hidden="true" />
        <dt class="w-24 shrink-0 text-surface-600 dark:text-surface-300">{{ row.label }}</dt>
        <dd class="m-0 min-w-0 flex-1 text-right text-surface-900 [overflow-wrap:anywhere] dark:text-surface-0">
          {{ row.value }}
          <span v-if="row.detail" class="block text-xs text-surface-500 dark:text-surface-400">{{ row.detail }}</span>
        </dd>
      </div>
    </dl>

    <p
      v-if="item.description"
      class="m-0 whitespace-pre-line text-sm text-surface-700 [overflow-wrap:anywhere] dark:text-surface-200"
    >
      {{ item.description }}
    </p>
  </div>
</template>

<script setup>
import { isSameDay } from 'date-fns'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { agendaSourceFor, agendaSourceLabel } from '../../../constants/agendaSources.js'
import { rangeArrow } from '../../../i18n/formatLocale.js'
import { toAgendaDate } from '../../../utils/agendaDate.js'
import { agendaItemTimeLabel } from '../../../utils/agendaItem.js'
import { formatDateCompactWithYear, formatDateWithWeekday } from '../../../utils/date.js'

/**
 * A manual entry as a phone first shows it: what, when, how often and where, read before it is
 * edited. The availability panel follows it in the drawer.
 */
const props = defineProps({
  item: { type: Object, required: true }
})

const { t, te } = useI18n()

const isAllDay = computed(() => props.item.is_all_day === true)

const dayLabel = computed(() => {
  const start = toAgendaDate(props.item.datetime, isAllDay.value)
  const end = toAgendaDate(props.item.end_datetime, isAllDay.value)
  if (!start) return ''
  if (!end || isSameDay(start, end)) return formatDateWithWeekday(start)

  return `${formatDateWithWeekday(start)} ${rangeArrow()} ${formatDateWithWeekday(end)}`
})

// A frequency the client does not know yet reads as itself rather than as a missing key.
function frequencyLabel(frequency) {
  const key = `band_space_agenda.recurrence.${frequency}`
  return te(key) ? t(key) : frequency
}

const rows = computed(() => {
  const metadata = props.item.metadata ?? {}
  const rows = [
    {
      key: 'date',
      icon: 'pi-calendar',
      label: t('band_space_agenda.entry.date_label'),
      value: dayLabel.value
    },
    {
      key: 'time',
      icon: 'pi-clock',
      label: t('band_space_agenda.phone.time_label'),
      value: agendaItemTimeLabel(props.item) ?? t('band_space_agenda.common.all_day')
    }
  ]
  if (metadata.recurrence_frequency) {
    rows.push({
      key: 'repeat',
      icon: 'pi-replay',
      label: t('band_space_agenda.recurrence.label'),
      value: frequencyLabel(metadata.recurrence_frequency),
      detail: metadata.recurrence_until_date
        ? t('band_space_agenda.phone.until', {
            date: formatDateCompactWithYear(metadata.recurrence_until_date)
          })
        : null
    })
  }
  if (metadata.location) {
    rows.push({
      key: 'place',
      icon: 'pi-map-marker',
      label: t('band_space_agenda.entry.location_label'),
      value: metadata.location
    })
  }

  return rows
})
</script>
