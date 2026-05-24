<template>
  <Drawer
    v-model:visible="isVisible"
    position="right"
    :header="isEditMode ? 'Modifier un événement' : 'Nouvel événement'"
    class="w-full md:w-[28rem]"
  >
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

      <div class="flex flex-col gap-1">
        <label for="agenda-title" class="text-sm font-medium">
          Titre <span class="text-red-500">*</span>
        </label>
        <InputText
          id="agenda-title"
          v-model="form.title"
          placeholder="Ex : Répétition générale"
          autofocus
          :class="{ 'p-invalid': fieldErrors.title }"
        />
        <small v-if="fieldErrors.title" class="text-red-500">{{ fieldErrors.title }}</small>
      </div>

      <div class="flex items-center gap-2">
        <Checkbox v-model="form.isAllDay" inputId="agenda-all-day" binary />
        <label for="agenda-all-day" class="text-sm font-medium select-none">Toute la journée</label>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-datetime" class="text-sm font-medium">
          {{ form.isAllDay ? 'Date' : 'Date et heure' }} <span class="text-red-500">*</span>
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
            placeholder="HH:MM"
            class="w-24 flex-shrink-0"
            aria-label="Heure de début"
          />
        </div>
        <small v-if="fieldErrors.eventDatetime" class="text-red-500">
          {{ fieldErrors.eventDatetime }}
        </small>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-end-datetime" class="text-sm font-medium">
          {{ form.isAllDay ? 'Dernier jour (optionnel)' : 'Fin (optionnel)' }}
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
            placeholder="HH:MM"
            class="w-24 flex-shrink-0"
            aria-label="Heure de fin"
          />
        </div>
        <small v-if="fieldErrors.endDatetime" class="text-red-500">
          {{ fieldErrors.endDatetime }}
        </small>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-location" class="text-sm font-medium">Lieu</label>
        <InputText
          id="agenda-location"
          v-model="form.location"
          placeholder="Ex : Studio, Zenith de Paris…"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-description" class="text-sm font-medium">Description</label>
        <Textarea
          id="agenda-description"
          v-model="form.description"
          rows="3"
          autoResize
          placeholder="Notes complémentaires"
        />
      </div>

      <div class="flex flex-col gap-3 border border-surface-200 dark:border-surface-700 rounded-md p-3">
        <div class="flex flex-col gap-1">
          <label for="agenda-recurrence-frequency" class="text-sm font-medium">Répéter</label>
          <Select
            id="agenda-recurrence-frequency"
            v-model="form.recurrenceFrequency"
            :options="frequencyOptions"
            option-label="label"
            option-value="value"
          />
        </div>

        <div v-if="form.recurrenceFrequency === 'monthly'" class="flex flex-col gap-2">
          <span class="text-sm font-medium">Mode mensuel</span>
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
            Calculé d'après la date de l'événement.
          </small>
        </div>

        <div v-if="form.recurrenceFrequency" class="flex flex-col gap-1">
          <label for="agenda-recurrence-until" class="text-sm font-medium">
            Jusqu'au <span class="text-red-500">*</span>
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
          :label="isEditMode ? 'Enregistrer' : 'Créer'"
          :loading="agendaStore.isSaving"
        />
        <Button
          type="button"
          label="Annuler"
          severity="secondary"
          text
          @click="isVisible = false"
        />
        <Button
          v-if="isEditMode"
          type="button"
          label="Supprimer"
          severity="danger"
          text
          class="ml-auto"
          :loading="agendaStore.isDeleting"
          @click="handleDelete"
        />
      </div>
    </form>
  </Drawer>

  <Dialog
    v-model:visible="showDeleteScopeDialog"
    modal
    header="Supprimer l’événement récurrent"
    :style="{ width: '28rem' }"
    :closable="!agendaStore.isDeleting"
  >
    <p class="text-sm text-surface-700 dark:text-surface-200 mb-4">
      Cet événement fait partie d’une série. Que souhaites-tu supprimer&nbsp;?
    </p>
    <div class="flex flex-col gap-3">
      <div class="flex items-start gap-2">
        <RadioButton v-model="deleteScope" inputId="scope-single" value="single" />
        <label for="scope-single" class="text-sm cursor-pointer select-none">
          <span class="font-medium">Cette occurrence seulement</span>
          <span class="block text-xs text-surface-500">Le reste de la série reste en place.</span>
        </label>
      </div>
      <div class="flex items-start gap-2">
        <RadioButton v-model="deleteScope" inputId="scope-from" value="from" />
        <label for="scope-from" class="text-sm cursor-pointer select-none">
          <span class="font-medium">Cette occurrence et les suivantes</span>
          <span class="block text-xs text-surface-500">La série se termine la veille de cette occurrence.</span>
        </label>
      </div>
      <div class="flex items-start gap-2">
        <RadioButton v-model="deleteScope" inputId="scope-all" value="all" />
        <label for="scope-all" class="text-sm cursor-pointer select-none">
          <span class="font-medium">Toute la série</span>
          <span class="block text-xs text-surface-500">Toutes les occurrences (passées et futures) seront supprimées.</span>
        </label>
      </div>
    </div>
    <template #footer>
      <Button
        label="Annuler"
        severity="secondary"
        text
        :disabled="agendaStore.isDeleting"
        @click="showDeleteScopeDialog = false"
      />
      <Button
        label="Supprimer"
        severity="danger"
        :loading="agendaStore.isDeleting"
        @click="confirmScopedDelete"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { format } from 'date-fns'
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
import { useBandAgendaStore } from '../../../store/bandSpace/bandSpaceAgenda.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  agendaItem: { type: Object, default: null },
  initialDatetime: { type: Date, default: null }
})

const emit = defineEmits(['saved', 'deleted'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

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
  recurrenceFrequency: null,
  recurrenceUntilDate: null,
  recurrenceMonthlyMode: null
})

const frequencyOptions = [
  { value: null, label: 'Ne se répète pas' },
  { value: 'daily', label: 'Quotidien' },
  { value: 'weekly', label: 'Hebdomadaire' },
  { value: 'monthly', label: 'Mensuel' },
  { value: 'yearly', label: 'Annuel' }
]

const WEEKDAY_LABELS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi']
const ORDINAL_LABELS = ['premier', 'deuxième', 'troisième', 'quatrième', 'cinquième']

const monthlyByDateLabel = computed(() => {
  if (!form.eventDatetime) return 'Le même jour du mois'
  return `Le ${form.eventDatetime.getDate()} du mois`
})

const monthlyByWeekdayLabel = computed(() => {
  if (!form.eventDatetime) return 'Même jour de la semaine du mois'
  const weekIndex = Math.floor((form.eventDatetime.getDate() - 1) / 7)
  const ordinal = ORDINAL_LABELS[weekIndex] ?? `${weekIndex + 1}ème`
  return `Le ${ordinal} ${WEEKDAY_LABELS[form.eventDatetime.getDay()]} du mois`
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

  skipShiftEnd = true
  if (props.agendaItem && props.agendaItem.source === 'manual') {
    form.title = props.agendaItem.title ?? ''
    form.eventDatetime = props.agendaItem.datetime ? new Date(props.agendaItem.datetime) : null
    form.endDatetime = props.agendaItem.end_datetime
      ? new Date(props.agendaItem.end_datetime)
      : null
    form.isAllDay = !!props.agendaItem.is_all_day
    form.location = props.agendaItem.metadata?.location ?? ''
    form.description = props.agendaItem.description ?? ''
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
    fieldErrors.endDatetime = 'La fin doit être postérieure au début'
    return
  }

  if (form.recurrenceFrequency !== null) {
    if (!form.recurrenceUntilDate) {
      fieldErrors.recurrenceUntilDate = 'Veuillez spécifier une date de fin de récurrence.'
      return
    }
    if (form.eventDatetime && form.recurrenceUntilDate < form.eventDatetime) {
      fieldErrors.recurrenceUntilDate =
        'La date de fin doit être postérieure ou égale au premier événement.'
      return
    }
    if (form.recurrenceFrequency === 'monthly' && !form.recurrenceMonthlyMode) {
      fieldErrors.recurrenceMonthlyMode = 'Veuillez préciser le mode de récurrence mensuelle.'
      return
    }
  }

  const serializeStart = () => {
    if (!form.eventDatetime) return null
    return form.isAllDay
      ? format(form.eventDatetime, 'yyyy-MM-dd')
      : form.eventDatetime.toISOString()
  }
  const serializeEnd = () => {
    if (!form.endDatetime) return null
    return form.isAllDay ? format(form.endDatetime, 'yyyy-MM-dd') : form.endDatetime.toISOString()
  }

  const payload = {
    title: form.title.trim(),
    eventDatetime: serializeStart(),
    endDatetime: serializeEnd(),
    isAllDay: form.isAllDay,
    location: form.location.trim() === '' ? null : form.location.trim(),
    description: form.description.trim() === '' ? null : form.description.trim(),
    recurrenceFrequency: form.recurrenceFrequency,
    recurrenceUntilDate:
      form.recurrenceFrequency && form.recurrenceUntilDate
        ? format(form.recurrenceUntilDate, 'yyyy-MM-dd')
        : null,
    recurrenceMonthlyMode:
      form.recurrenceFrequency === 'monthly' ? form.recurrenceMonthlyMode : null
  }

  try {
    if (isEditMode.value) {
      await agendaStore.updateEntry(props.bandSpaceId, props.agendaItem.source_id, payload)
    } else {
      await agendaStore.createEntry(props.bandSpaceId, payload)
    }
    emit('saved')
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
    formError.value = error?.message ?? 'Impossible d’enregistrer l’événement'
  }
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
    message: 'Es-tu sûr de vouloir supprimer cet événement ?',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await agendaStore.deleteEntry(props.bandSpaceId, props.agendaItem.source_id)
        emit('deleted')
        isVisible.value = false
      } catch (error) {
        formError.value = error?.message ?? 'Impossible de supprimer l’événement'
      }
    }
  })
}

function pickedOccurrenceDate() {
  // The expanded occurrence id from AgendaAggregator carries the occurrence's
  // local date in its .datetime ATOM string. Slice off the YYYY-MM-DD prefix.
  return props.agendaItem?.datetime?.slice(0, 10) ?? null
}

async function confirmScopedDelete() {
  const seriesId = props.agendaItem?.metadata?.series_id ?? props.agendaItem?.source_id
  const occurrenceDate = pickedOccurrenceDate()

  try {
    if (deleteScope.value === 'single') {
      await agendaStore.deleteOccurrence(props.bandSpaceId, seriesId, occurrenceDate)
    } else if (deleteScope.value === 'from') {
      await agendaStore.deleteFromOccurrence(props.bandSpaceId, seriesId, occurrenceDate)
    } else {
      await agendaStore.deleteEntry(props.bandSpaceId, seriesId)
    }
    showDeleteScopeDialog.value = false
    emit('deleted')
    isVisible.value = false
  } catch (error) {
    formError.value = error?.message ?? 'Impossible de supprimer l’événement'
    showDeleteScopeDialog.value = false
  }
}
</script>
