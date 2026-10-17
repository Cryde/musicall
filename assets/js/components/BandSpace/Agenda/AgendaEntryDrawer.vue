<template>
  <Drawer
    v-model:visible="isVisible"
    position="right"
    :header="isEditMode ? t('band_space_agenda.entry.edit_title') : t('band_space_agenda.common.new_event')"
    class="w-full! md:w-[28rem]!"
  >
    <!--
      On a saved entry the switch acts at once, right above the block it shows: the block reads the
      stored answers, which the API only serves once the entry asks.
    -->
    <div v-if="isEditMode" class="flex flex-col gap-1 mb-3">
      <div class="flex items-center gap-2">
        <Checkbox
          :modelValue="form.askAvailability"
          inputId="agenda-ask-availability-saved"
          binary
          :disabled="isSavingAskAvailability"
          @update:modelValue="handleAskAvailabilityToggle"
        />
        <label for="agenda-ask-availability-saved" class="text-sm font-medium select-none">
          {{ t('band_space_agenda.entry.ask_availability') }}
        </label>
        <i
          v-if="isSavingAskAvailability"
          class="pi pi-spin pi-spinner text-xs text-surface-500"
          aria-hidden="true"
        />
      </div>
      <small v-if="askAvailabilityError" class="text-red-600 dark:text-red-400">
        {{ askAvailabilityError }}
      </small>
      <small v-else-if="!form.askAvailability" class="text-surface-600 dark:text-surface-300">
        {{ t('band_space_agenda.entry.ask_availability_kept') }}
      </small>
      <small v-if="isSeriesOccurrence" class="text-surface-600 dark:text-surface-300">
        {{ t('band_space_agenda.entry.ask_availability_series') }}
      </small>
    </div>

    <AgendaAvailabilityPanel
      v-if="availabilityOccurrenceDate"
      :bandSpaceId="bandSpaceId"
      :entryId="agendaItem.source_id"
      :occurrenceDate="availabilityOccurrenceDate"
      :dateLabel="isSeriesOccurrence ? occurrenceDateLabel : null"
      class="mb-4"
      @changed="emit('availability-changed')"
    />

    <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
      <Message
        v-if="formError"
        severity="error"
        :closable="true"
        class="text-sm"
        @close="formError = null"
      >
        {{ formError }}
      </Message>

      <!--
        A recurring entry has no per-occurrence storage, so this form always edits the series. Say so
        before anything is typed rather than after the fact, and name the series start: it is the one
        thing the occurrence on screen does not reveal.
      -->
      <div
        v-if="isSeriesOccurrence"
        class="flex gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-100"
      >
        <i class="pi pi-info-circle mt-0.5 flex-shrink-0" aria-hidden="true" />
        <div>
          <p>{{ t('band_space_agenda.entry.series_notice') }}</p>
          <p class="mt-1">{{ t('band_space_agenda.entry.series_start', { date: seriesStartLabel }) }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-title" class="text-sm font-medium">
          {{ t('band_space_agenda.entry.title_label') }} <span class="text-red-500">*</span>
        </label>
        <InputText
          id="agenda-title"
          v-model="form.title"
          :placeholder="t('band_space_agenda.entry.title_placeholder')"
          autofocus
          :class="{ 'p-invalid': fieldErrors.title }"
        />
        <small v-if="fieldErrors.title" class="text-red-500">{{ fieldErrors.title }}</small>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox v-model="form.isAllDay" inputId="agenda-all-day" binary />
        <label for="agenda-all-day" class="text-sm font-medium select-none">{{ t('band_space_agenda.common.all_day') }}</label>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-datetime" class="text-sm font-medium">
          {{ form.isAllDay ? t('band_space_agenda.entry.date_label') : t('band_space_agenda.entry.datetime_label') }} <span class="text-red-500">*</span>
        </label>
        <div class="flex gap-2">
          <DatePicker
            id="agenda-datetime"
            v-model="form.eventDatetime"
            dateFormat="dd/mm/yy"
            showIcon
            class="flex-1 min-w-0"
            :class="{ 'p-invalid': fieldErrors.eventDatetime }"
          />
          <InputMask
            v-if="!form.isAllDay"
            v-model="eventTimeText"
            mask="99:99"
            :placeholder="t('band_space_agenda.entry.time_placeholder')"
            class="w-24 flex-shrink-0"
            :aria-label="t('band_space_agenda.entry.start_time_aria')"
          />
        </div>
        <small v-if="fieldErrors.eventDatetime" class="text-red-500">
          {{ fieldErrors.eventDatetime }}
        </small>
        <small v-if="isSeriesOccurrence" class="text-surface-500 dark:text-surface-400">
          {{ t('band_space_agenda.entry.occurrence_date_hint') }}
        </small>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-end-datetime" class="text-sm font-medium">
          {{ form.isAllDay ? t('band_space_agenda.entry.last_day_label') : t('band_space_agenda.entry.end_label') }}
        </label>
        <div class="flex gap-2">
          <DatePicker
            id="agenda-end-datetime"
            v-model="form.endDatetime"
            dateFormat="dd/mm/yy"
            showIcon
            showButtonBar
            :minDate="form.eventDatetime ?? undefined"
            class="flex-1 min-w-0"
            :class="{ 'p-invalid': fieldErrors.endDatetime }"
          />
          <InputMask
            v-if="!form.isAllDay"
            v-model="endTimeText"
            mask="99:99"
            :placeholder="t('band_space_agenda.entry.time_placeholder')"
            class="w-24 flex-shrink-0"
            :aria-label="t('band_space_agenda.entry.end_time_aria')"
          />
        </div>
        <small v-if="fieldErrors.endDatetime" class="text-red-500">
          {{ fieldErrors.endDatetime }}
        </small>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-location" class="text-sm font-medium">{{ t('band_space_agenda.entry.location_label') }}</label>
        <InputText
          id="agenda-location"
          v-model="form.location"
          :placeholder="t('band_space_agenda.entry.location_placeholder')"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-description" class="text-sm font-medium">{{ t('band_space_agenda.entry.description_label') }}</label>
        <Textarea
          id="agenda-description"
          v-model="form.description"
          rows="3"
          autoResize
          :placeholder="t('band_space_agenda.entry.description_placeholder')"
        />
      </div>

      <div v-if="!isEditMode" class="flex items-center gap-2">
        <Checkbox v-model="form.askAvailability" inputId="agenda-ask-availability" binary />
        <label for="agenda-ask-availability" class="text-sm font-medium select-none">
          {{ t('band_space_agenda.entry.ask_availability') }}
        </label>
      </div>

      <div class="flex flex-col gap-3 border border-surface-200 dark:border-surface-700 rounded-md p-3">
        <div class="flex flex-col gap-1">
          <label for="agenda-recurrence-frequency" class="text-sm font-medium">{{ t('band_space_agenda.recurrence.label') }}</label>
          <Select
            id="agenda-recurrence-frequency"
            v-model="form.recurrenceFrequency"
            :options="frequencyOptions"
            option-label="label"
            option-value="value"
          />
        </div>

        <div v-if="form.recurrenceFrequency === 'monthly'" class="flex flex-col gap-2">
          <span class="text-sm font-medium">{{ t('band_space_agenda.recurrence.monthly_mode') }}</span>
          <div class="flex items-center gap-2">
            <RadioButton
              v-model="form.recurrenceMonthlyMode"
              inputId="agenda-recurrence-by-date"
              name="recurrence-monthly-mode"
              value="by_date"
            />
            <label for="agenda-recurrence-by-date" class="text-sm select-none">
              {{ monthlyByDateLabel }}
            </label>
          </div>
          <div class="flex items-center gap-2">
            <RadioButton
              v-model="form.recurrenceMonthlyMode"
              inputId="agenda-recurrence-by-weekday"
              name="recurrence-monthly-mode"
              value="by_weekday"
            />
            <label for="agenda-recurrence-by-weekday" class="text-sm select-none">
              {{ monthlyByWeekdayLabel }}
            </label>
          </div>
          <small v-if="fieldErrors.recurrenceMonthlyMode" class="text-red-500">
            {{ fieldErrors.recurrenceMonthlyMode }}
          </small>
          <small class="text-surface-500 dark:text-surface-400">
            {{ t('band_space_agenda.recurrence.monthly_hint') }}
          </small>
        </div>

        <div v-if="form.recurrenceFrequency" class="flex flex-col gap-1">
          <label for="agenda-recurrence-until" class="text-sm font-medium">
            {{ t('band_space_agenda.recurrence.until_label') }} <span class="text-red-500">*</span>
          </label>
          <DatePicker
            id="agenda-recurrence-until"
            v-model="form.recurrenceUntilDate"
            dateFormat="dd/mm/yy"
            showIcon
            :minDate="form.eventDatetime ?? undefined"
            :class="{ 'p-invalid': fieldErrors.recurrenceUntilDate }"
          />
          <small v-if="fieldErrors.recurrenceUntilDate" class="text-red-500">
            {{ fieldErrors.recurrenceUntilDate }}
          </small>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2 mt-4">
        <Button
          type="submit"
          :label="isEditMode ? t('band_space_agenda.common.save') : t('band_space_agenda.entry.create')"
          :loading="agendaStore.isSaving"
        />
        <Button
          type="button"
          :label="t('band_space_agenda.common.cancel')"
          severity="secondary"
          text
          @click="isVisible = false"
        />
        <Button
          v-if="isEditMode"
          type="button"
          :label="t('band_space_agenda.common.delete')"
          severity="danger"
          text
          class="ml-auto"
          :loading="agendaStore.isDeleting"
          @click="handleDelete"
        />
      </div>
    </form>
  </Drawer>

  <!--
    Only shown for the two saves that restructure the series, so a typo fix stays a single click.
    There is no scope choice to make: "cette occurrence seulement" would need a per-occurrence
    override the model does not have, and offering it would mean writing the whole series anyway.
  -->
  <Dialog
    v-model:visible="showSeriesSaveDialog"
    modal
    :header="seriesSaveDialog.header"
    :style="{ width: '28rem' }"
    :closable="!agendaStore.isSaving"
  >
    <p class="text-sm text-surface-700 dark:text-surface-200">
      {{ seriesSaveDialog.message }}
    </p>
    <p class="text-sm text-surface-500 dark:text-surface-400 mt-2">
      {{ seriesSaveDialog.detail }}
    </p>
    <template #footer>
      <Button
        :label="t('band_space_agenda.common.cancel')"
        severity="secondary"
        text
        :disabled="agendaStore.isSaving"
        @click="showSeriesSaveDialog = false"
      />
      <Button
        :label="seriesSaveDialog.acceptLabel"
        :severity="seriesSaveDialog.acceptSeverity"
        :loading="agendaStore.isSaving"
        @click="confirmSeriesSave"
      />
    </template>
  </Dialog>

  <Dialog
    v-model:visible="showDeleteScopeDialog"
    modal
    :header="t('band_space_agenda.delete_scope.header')"
    :style="{ width: '28rem' }"
    :closable="!agendaStore.isDeleting"
  >
    <p class="text-sm text-surface-700 dark:text-surface-200 mb-4">
      {{ t('band_space_agenda.delete_scope.question') }}
    </p>
    <div class="flex flex-col gap-3">
      <div class="flex items-start gap-2">
        <RadioButton v-model="deleteScope" inputId="scope-single" value="single" />
        <label for="scope-single" class="text-sm cursor-pointer select-none">
          <span class="font-medium">{{ t('band_space_agenda.delete_scope.single') }}</span>
          <span class="block text-xs text-surface-500">{{ t('band_space_agenda.delete_scope.single_hint') }}</span>
        </label>
      </div>
      <div class="flex items-start gap-2">
        <RadioButton v-model="deleteScope" inputId="scope-from" value="from" />
        <label for="scope-from" class="text-sm cursor-pointer select-none">
          <span class="font-medium">{{ t('band_space_agenda.delete_scope.from') }}</span>
          <span class="block text-xs text-surface-500">{{ t('band_space_agenda.delete_scope.from_hint') }}</span>
        </label>
      </div>
      <div class="flex items-start gap-2">
        <RadioButton v-model="deleteScope" inputId="scope-all" value="all" />
        <label for="scope-all" class="text-sm cursor-pointer select-none">
          <span class="font-medium">{{ t('band_space_agenda.delete_scope.all') }}</span>
          <span class="block text-xs text-surface-500">{{ t('band_space_agenda.delete_scope.all_hint') }}</span>
        </label>
      </div>
    </div>
    <template #footer>
      <Button
        :label="t('band_space_agenda.common.cancel')"
        severity="secondary"
        text
        :disabled="agendaStore.isDeleting"
        @click="showDeleteScopeDialog = false"
      />
      <Button
        :label="t('band_space_agenda.common.delete')"
        severity="danger"
        :loading="agendaStore.isDeleting"
        @click="confirmScopedDelete"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { differenceInCalendarDays, format } from 'date-fns'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import Drawer from 'primevue/drawer'
import InputMask from 'primevue/inputmask'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import RadioButton from 'primevue/radiobutton'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceAgendaApi from '../../../api/bandSpace/band-space-agenda.js'
import { dateFnsLocale, datePattern } from '../../../i18n/formatLocale.js'
import { useBandAgendaStore } from '../../../store/bandSpace/bandSpaceAgenda.js'
import { seriesCutDate, toAgendaDate } from '../../../utils/agendaDate.js'
import {
  agendaSeriesSubmission,
  SERIES_IMPACT_NONE,
  SERIES_IMPACT_RECURRENCE_REMOVED
} from '../../../utils/agendaSeriesEdit.js'
import { recordOpenedItem } from '../../../utils/openedItemRecorder.js'
import AgendaAvailabilityPanel from './AgendaAvailabilityPanel.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  agendaItem: { type: Object, default: null },
  initialDatetime: { type: Date, default: null }
})

const emit = defineEmits(['saved', 'deleted', 'availability-changed'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const agendaStore = useBandAgendaStore()
const confirm = useConfirm()

const formError = ref(null)
const fieldErrors = reactive({
  title: null,
  eventDatetime: null,
  endDatetime: null,
  recurrenceUntilDate: null,
  recurrenceMonthlyMode: null
})

const form = reactive({
  title: '',
  eventDatetime: null,
  endDatetime: null,
  isAllDay: false,
  location: '',
  description: '',
  askAvailability: true,
  recurrenceFrequency: null,
  recurrenceUntilDate: null,
  recurrenceMonthlyMode: null
})

const frequencyOptions = computed(() => [
  { value: null, label: t('band_space_agenda.recurrence.none') },
  { value: 'daily', label: t('band_space_agenda.recurrence.daily') },
  { value: 'weekly', label: t('band_space_agenda.recurrence.weekly') },
  { value: 'monthly', label: t('band_space_agenda.recurrence.monthly') },
  { value: 'yearly', label: t('band_space_agenda.recurrence.yearly') }
])

const WEEKDAY_LABEL_KEYS = [
  'band_space_agenda.recurrence.weekday.sunday',
  'band_space_agenda.recurrence.weekday.monday',
  'band_space_agenda.recurrence.weekday.tuesday',
  'band_space_agenda.recurrence.weekday.wednesday',
  'band_space_agenda.recurrence.weekday.thursday',
  'band_space_agenda.recurrence.weekday.friday',
  'band_space_agenda.recurrence.weekday.saturday'
]
const ORDINAL_LABEL_KEYS = [
  'band_space_agenda.recurrence.ordinal.first',
  'band_space_agenda.recurrence.ordinal.second',
  'band_space_agenda.recurrence.ordinal.third',
  'band_space_agenda.recurrence.ordinal.fourth',
  'band_space_agenda.recurrence.ordinal.fifth'
]

const monthlyByDateLabel = computed(() => {
  if (!form.eventDatetime) return t('band_space_agenda.recurrence.by_date_default')
  return t('band_space_agenda.recurrence.by_date', { day: form.eventDatetime.getDate() })
})

const monthlyByWeekdayLabel = computed(() => {
  if (!form.eventDatetime) return t('band_space_agenda.recurrence.by_weekday_default')
  const weekIndex = Math.floor((form.eventDatetime.getDate() - 1) / 7)
  const ordinalKey = ORDINAL_LABEL_KEYS[weekIndex]
  const ordinal = ordinalKey
    ? t(ordinalKey)
    : t('band_space_agenda.recurrence.ordinal.nth', { number: weekIndex + 1 })
  return t('band_space_agenda.recurrence.by_weekday', {
    ordinal,
    weekday: t(WEEKDAY_LABEL_KEYS[form.eventDatetime.getDay()])
  })
})

// Clearing the frequency must also clear dependent fields so the next submit doesn't
// drag stale values along.
watch(
  () => form.recurrenceFrequency,
  (newFreq, oldFreq) => {
    if (newFreq === null) {
      form.recurrenceUntilDate = null
      form.recurrenceMonthlyMode = null
      return
    }
    if (oldFreq === null) {
      // Default the until date to one month after the event so users have something to tweak.
      if (form.eventDatetime && form.recurrenceUntilDate === null) {
        const seed = new Date(form.eventDatetime)
        seed.setMonth(seed.getMonth() + 1)
        form.recurrenceUntilDate = seed
      }
    }
    if (newFreq !== 'monthly') {
      form.recurrenceMonthlyMode = null
    } else if (form.recurrenceMonthlyMode === null) {
      form.recurrenceMonthlyMode = 'by_date'
    }
  }
)

const isEditMode = computed(() => props.agendaItem !== null && props.agendaItem.source === 'manual')

// The member's « Ouverts par moi » history (#1047), under the series id, which is what the palette
// opens. The id is watched too, for an entry opened while the drawer is still showing another.
watch(
  [isVisible, () => (isEditMode.value ? props.agendaItem.source_id : null)],
  ([visible, entryId]) => {
    if (visible && entryId) {
      recordOpenedItem(props.bandSpaceId, 'agenda', entryId)
    }
  }
)

// The agenda expands a series into occurrences that all share one `source_id`, so editing any of
// them edits the series. `datetime` is the occurrence on screen, `series_start_datetime` the anchor
// it was expanded from.
const isSeriesOccurrence = computed(
  () => isEditMode.value && props.agendaItem.metadata?.is_recurring_occurrence === true
)
const isAllDayEntry = computed(() => props.agendaItem?.is_all_day === true)
const seriesAnchorStart = computed(() =>
  toAgendaDate(props.agendaItem?.metadata?.series_start_datetime, isAllDayEntry.value)
)
const occurrenceStart = computed(() =>
  toAgendaDate(props.agendaItem?.datetime, isAllDayEntry.value)
)
const seriesStartLabel = computed(() => formatEventMoment(seriesAnchorStart.value))

// Who can make it is asked per occurrence, so only an entry already saved, and asking, has one.
// In edit mode form.askAvailability is the stored value: the switch saves it as soon as it moves.
const availabilityOccurrenceDate = computed(() =>
  isEditMode.value && form.askAvailability
    ? (props.agendaItem.metadata?.occurrence_date ?? null)
    : null
)

const isSavingAskAvailability = ref(false)
const askAvailabilityError = ref(null)

async function handleAskAvailabilityToggle(askAvailability) {
  isSavingAskAvailability.value = true
  askAvailabilityError.value = null
  try {
    await bandSpaceAgendaApi.updateEntry(props.bandSpaceId, props.agendaItem.source_id, {
      askAvailability
    })
    form.askAvailability = askAvailability
    emit('availability-changed')
  } catch (error) {
    askAvailabilityError.value =
      error?.message ?? t('band_space_agenda.entry.ask_availability_error')
  } finally {
    isSavingAskAvailability.value = false
  }
}
// Read from the item rather than the form, so editing the date pickers does not relabel answers
// that still belong to the occurrence as saved.
const occurrenceDateLabel = computed(() => {
  if (!occurrenceStart.value) return null
  return isAllDayEntry.value
    ? format(occurrenceStart.value, 'EEEE d MMMM yyyy', { locale: dateFnsLocale() })
    : format(occurrenceStart.value, datePattern('weekdayDateTime'), { locale: dateFnsLocale() })
})

function formatEventMoment(date) {
  if (!date) return ''
  return form.isAllDay
    ? format(date, 'EEEE d MMMM yyyy', { locale: dateFnsLocale() })
    : format(date, datePattern('weekdayDateTime'), { locale: dateFnsLocale() })
}

const eventTimeText = computed({
  get: () => (form.eventDatetime ? format(form.eventDatetime, 'HH:mm') : ''),
  set: (val) => {
    const parsed = parseTimeText(val)
    if (!parsed || !form.eventDatetime) return
    form.eventDatetime = withTime(form.eventDatetime, parsed.hours, parsed.minutes)
  }
})

const endTimeText = computed({
  get: () => (form.endDatetime ? format(form.endDatetime, 'HH:mm') : ''),
  set: (val) => {
    const parsed = parseTimeText(val)
    if (!parsed || !form.endDatetime) return
    form.endDatetime = withTime(form.endDatetime, parsed.hours, parsed.minutes)
  }
})

function parseTimeText(val) {
  if (!val || typeof val !== 'string') return null
  const match = val.match(/^(\d{1,2}):(\d{2})$/)
  if (!match) return null
  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null
  return { hours, minutes }
}

let skipShiftEnd = false

watch(
  () => form.isAllDay,
  (allDay, wasAllDay) => {
    if (wasAllDay && !allDay) {
      // Coming back from all-day: seed sensible default times so users don't see 00:00.
      if (form.eventDatetime) form.eventDatetime = withTime(form.eventDatetime, 9, 0)
      if (form.endDatetime) form.endDatetime = withTime(form.endDatetime, 10, 0)
    }
  }
)

watch(
  () => form.eventDatetime,
  (newStart, oldStart) => {
    if (skipShiftEnd) return
    if (!newStart || !oldStart || !form.endDatetime) return
    // Preserve the original duration when the start moves, so the end follows.
    const delta = newStart.getTime() - oldStart.getTime()
    if (delta !== 0) {
      form.endDatetime = new Date(form.endDatetime.getTime() + delta)
    }
  }
)

function withTime(date, hours, minutes) {
  const next = new Date(date)
  next.setHours(hours, minutes, 0, 0)
  return next
}

watch(isVisible, (visible) => {
  if (!visible) return

  formError.value = null
  fieldErrors.title = null
  fieldErrors.eventDatetime = null
  fieldErrors.endDatetime = null
  fieldErrors.recurrenceUntilDate = null
  fieldErrors.recurrenceMonthlyMode = null
  showSeriesSaveDialog.value = false
  pendingSubmission.value = null
  askAvailabilityError.value = null

  skipShiftEnd = true
  if (props.agendaItem && props.agendaItem.source === 'manual') {
    form.title = props.agendaItem.title ?? ''
    // The pickers are filled with the day as written, not with the instant: serializeDatetime
    // writes an all day entry back with local getters, so seeding them from the UTC midnight pin
    // would move the entry one day earlier on every save west of UTC.
    form.eventDatetime = toAgendaDate(props.agendaItem.datetime, isAllDayEntry.value)
    form.endDatetime = toAgendaDate(props.agendaItem.end_datetime, isAllDayEntry.value)
    form.isAllDay = !!props.agendaItem.is_all_day
    form.location = props.agendaItem.metadata?.location ?? ''
    form.description = props.agendaItem.description ?? ''
    form.askAvailability = props.agendaItem.metadata?.ask_availability === true
    form.recurrenceFrequency = props.agendaItem.metadata?.recurrence_frequency ?? null
    form.recurrenceMonthlyMode = props.agendaItem.metadata?.recurrence_monthly_mode ?? null
    form.recurrenceUntilDate = props.agendaItem.metadata?.recurrence_until_date
      ? new Date(`${props.agendaItem.metadata.recurrence_until_date}T00:00:00`)
      : null
  } else {
    form.title = ''
    form.eventDatetime = props.initialDatetime ? new Date(props.initialDatetime) : null
    form.endDatetime = null
    form.isAllDay = false
    form.location = ''
    form.description = ''
    form.askAvailability = true
    form.recurrenceFrequency = null
    form.recurrenceUntilDate = null
    form.recurrenceMonthlyMode = null
  }
  nextTick(() => {
    skipShiftEnd = false
  })
})

async function handleSubmit() {
  formError.value = null
  fieldErrors.title = null
  fieldErrors.eventDatetime = null
  fieldErrors.endDatetime = null
  fieldErrors.recurrenceUntilDate = null
  fieldErrors.recurrenceMonthlyMode = null

  if (form.endDatetime && form.eventDatetime && form.endDatetime <= form.eventDatetime) {
    fieldErrors.endDatetime = t('band_space_agenda.entry.end_before_start')
    return
  }

  if (form.recurrenceFrequency !== null) {
    if (!form.recurrenceUntilDate) {
      fieldErrors.recurrenceUntilDate = t('band_space_agenda.recurrence.until_required')
      return
    }
    // Calendar days, like the server-side rule: the horizon is a date at midnight, so comparing it
    // as an instant rejects an evening event on the horizon day itself. That is the last occurrence
    // of a series, and it would be the one occurrence nobody could edit.
    if (
      form.eventDatetime &&
      differenceInCalendarDays(form.recurrenceUntilDate, form.eventDatetime) < 0
    ) {
      fieldErrors.recurrenceUntilDate = t('band_space_agenda.recurrence.until_before_start')
      return
    }
    if (form.recurrenceFrequency === 'monthly' && !form.recurrenceMonthlyMode) {
      fieldErrors.recurrenceMonthlyMode = t('band_space_agenda.recurrence.monthly_mode_required')
      return
    }
  }

  const submission = agendaSeriesSubmission({
    anchorStart: seriesAnchorStart.value,
    occurrenceStart: occurrenceStart.value,
    formStart: form.eventDatetime,
    formEnd: form.endDatetime,
    isAllDay: form.isAllDay,
    keepsRecurrence: form.recurrenceFrequency !== null
  })

  if (submission.impact === SERIES_IMPACT_NONE) {
    await persistEntry(submission)
    return
  }

  // Moving the series or collapsing it touches occurrences the user is not looking at, so it is
  // spelled out and confirmed first.
  pendingSubmission.value = submission
  showSeriesSaveDialog.value = true
}

function serializeDatetime(date) {
  if (!date) return null
  return form.isAllDay ? format(date, 'yyyy-MM-dd') : date.toISOString()
}

async function persistEntry(submission) {
  const payload = {
    title: form.title.trim(),
    // A start of `undefined` means the occurrence on screen was not moved: leaving the key out of
    // the merge patch is what keeps the series anchored where it is.
    ...(submission.start === undefined
      ? {}
      : { eventDatetime: serializeDatetime(submission.start) }),
    endDatetime: serializeDatetime(submission.end),
    isAllDay: form.isAllDay,
    location: form.location.trim() === '' ? null : form.location.trim(),
    description: form.description.trim() === '' ? null : form.description.trim(),
    askAvailability: form.askAvailability,
    recurrenceFrequency: form.recurrenceFrequency,
    recurrenceUntilDate:
      form.recurrenceFrequency && form.recurrenceUntilDate
        ? format(form.recurrenceUntilDate, 'yyyy-MM-dd')
        : null,
    recurrenceMonthlyMode:
      form.recurrenceFrequency === 'monthly' ? form.recurrenceMonthlyMode : null
  }

  try {
    // The saved entry travels with the event: the view needs its dates to tell whether what was
    // just saved is inside the period on screen.
    const saved = isEditMode.value
      ? await agendaStore.updateEntry(props.bandSpaceId, props.agendaItem.source_id, payload)
      : await agendaStore.createEntry(props.bandSpaceId, payload)
    emit('saved', saved)
    isVisible.value = false
  } catch (error) {
    if (error?.violationsByField) {
      if (error.violationsByField.title) {
        fieldErrors.title = error.violationsByField.title[0].message
      }
      if (error.violationsByField.event_datetime) {
        fieldErrors.eventDatetime = error.violationsByField.event_datetime[0].message
      }
      if (error.violationsByField.end_datetime) {
        fieldErrors.endDatetime = error.violationsByField.end_datetime[0].message
      }
      if (error.violationsByField.recurrence_until_date) {
        fieldErrors.recurrenceUntilDate = error.violationsByField.recurrence_until_date[0].message
      }
      if (error.violationsByField.recurrence_monthly_mode) {
        fieldErrors.recurrenceMonthlyMode =
          error.violationsByField.recurrence_monthly_mode[0].message
      }
    }
    formError.value = error?.message ?? t('band_space_agenda.entry.save_error')
  }
}

const showSeriesSaveDialog = ref(false)
const pendingSubmission = ref(null)

const seriesSaveDialog = computed(() => {
  const submission = pendingSubmission.value

  if (submission?.impact === SERIES_IMPACT_RECURRENCE_REMOVED) {
    return {
      header: t('band_space_agenda.series_save.remove_recurrence'),
      message: t('band_space_agenda.series_save.remove_recurrence_message', {
        date: formatEventMoment(submission.start)
      }),
      detail: t('band_space_agenda.series_save.remove_recurrence_detail'),
      acceptLabel: t('band_space_agenda.series_save.remove_recurrence'),
      acceptSeverity: 'danger'
    }
  }

  return {
    header: t('band_space_agenda.series_save.shift_header'),
    message: t('band_space_agenda.series_save.shift_message', {
      date: formatEventMoment(submission?.start),
      start: seriesStartLabel.value
    }),
    detail: t('band_space_agenda.series_save.shift_detail'),
    acceptLabel: t('band_space_agenda.series_save.shift_accept'),
    acceptSeverity: null
  }
})

async function confirmSeriesSave() {
  if (pendingSubmission.value === null) return

  // persistEntry reports its own failures in the drawer, so the dialog closes either way.
  await persistEntry(pendingSubmission.value)
  showSeriesSaveDialog.value = false
}

const showDeleteScopeDialog = ref(false)
const deleteScope = ref('single')

function handleDelete() {
  const isRecurring = props.agendaItem?.metadata?.is_recurring_occurrence === true
  if (isRecurring) {
    deleteScope.value = 'single'
    showDeleteScopeDialog.value = true
    return
  }

  confirm.require({
    message: t('band_space_agenda.entry.delete_confirm'),
    header: t('band_space_agenda.common.confirm_delete_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space_agenda.common.cancel'),
    acceptLabel: t('band_space_agenda.common.delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await agendaStore.deleteEntry(props.bandSpaceId, props.agendaItem.source_id)
        emit('deleted')
        isVisible.value = false
      } catch (error) {
        formError.value = error?.message ?? t('band_space_agenda.entry.delete_error')
      }
    }
  })
}

async function confirmScopedDelete() {
  const seriesId = props.agendaItem?.metadata?.series_id ?? props.agendaItem?.source_id

  try {
    if (deleteScope.value === 'single') {
      await agendaStore.deleteOccurrence(
        props.bandSpaceId,
        seriesId,
        props.agendaItem?.metadata?.occurrence_date
      )
    } else if (deleteScope.value === 'from') {
      await agendaStore.deleteFromOccurrence(
        props.bandSpaceId,
        seriesId,
        seriesCutDate(props.agendaItem)
      )
    } else {
      await agendaStore.deleteEntry(props.bandSpaceId, seriesId)
    }
    showDeleteScopeDialog.value = false
    emit('deleted')
    isVisible.value = false
  } catch (error) {
    formError.value = error?.message ?? t('band_space_agenda.entry.delete_error')
    showDeleteScopeDialog.value = false
  }
}
</script>
