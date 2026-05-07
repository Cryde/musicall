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
        <DatePicker
          id="agenda-datetime"
          v-model="form.eventDatetime"
          :showTime="!form.isAllDay"
          hourFormat="24"
          dateFormat="dd/mm/yy"
          showIcon
          :class="{ 'p-invalid': fieldErrors.eventDatetime }"
        />
        <small v-if="fieldErrors.eventDatetime" class="text-red-500">
          {{ fieldErrors.eventDatetime }}
        </small>
      </div>

      <div class="flex flex-col gap-1">
        <label for="agenda-end-datetime" class="text-sm font-medium">
          {{ form.isAllDay ? 'Dernier jour (optionnel)' : 'Fin (optionnel)' }}
        </label>
        <DatePicker
          id="agenda-end-datetime"
          v-model="form.endDatetime"
          :showTime="!form.isAllDay"
          hourFormat="24"
          dateFormat="dd/mm/yy"
          showIcon
          showButtonBar
          :minDate="form.eventDatetime ?? undefined"
          :class="{ 'p-invalid': fieldErrors.endDatetime }"
        />
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
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Textarea from 'primevue/textarea'
import { useConfirm } from 'primevue/useconfirm'
import { format } from 'date-fns'
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
  endDatetime: null
})

const form = reactive({
  title: '',
  eventDatetime: null,
  endDatetime: null,
  isAllDay: false,
  location: '',
  description: ''
})

const isEditMode = computed(() => props.agendaItem !== null && props.agendaItem.source === 'manual')

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

  skipShiftEnd = true
  if (props.agendaItem && props.agendaItem.source === 'manual') {
    form.title = props.agendaItem.title ?? ''
    form.eventDatetime = props.agendaItem.datetime ? new Date(props.agendaItem.datetime) : null
    form.endDatetime = props.agendaItem.end_datetime ? new Date(props.agendaItem.end_datetime) : null
    form.isAllDay = !!props.agendaItem.is_all_day
    form.location = props.agendaItem.metadata?.location ?? ''
    form.description = props.agendaItem.description ?? ''
  } else {
    form.title = ''
    form.eventDatetime = props.initialDatetime ? new Date(props.initialDatetime) : null
    form.endDatetime = null
    form.isAllDay = false
    form.location = ''
    form.description = ''
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

  if (form.endDatetime && form.eventDatetime && form.endDatetime <= form.eventDatetime) {
    fieldErrors.endDatetime = 'La fin doit être postérieure au début'
    return
  }

  const serializeStart = () => {
    if (!form.eventDatetime) return null
    return form.isAllDay ? format(form.eventDatetime, 'yyyy-MM-dd') : form.eventDatetime.toISOString()
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
    description: form.description.trim() === '' ? null : form.description.trim()
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
    }
    formError.value = error?.message ?? 'Impossible d’enregistrer l’événement'
  }
}

function handleDelete() {
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
</script>
