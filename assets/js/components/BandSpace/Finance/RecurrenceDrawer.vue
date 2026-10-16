<template>
  <Drawer
    v-model:visible="visibleModel"
    position="right"
    :header="isEditMode ? t('band_space_finance.recurrence_drawer.title_edit') : t('band_space_finance.recurrence_drawer.title_new')"
    class="w-full! md:w-[28rem]!"
  >
    <form class="flex flex-col gap-4" @submit.prevent="handleSave">
      <div v-if="!isEditMode" class="flex flex-col gap-1">
        <label for="recurrence-category" class="text-sm font-medium">{{ t('band_space_finance.common.category') }}</label>
        <Select
          id="recurrence-category"
          v-model="form.categoryId"
          :options="props.categories"
          optionLabel="name"
          optionValue="id"
          :placeholder="t('band_space_finance.common.select_category')"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-label" class="text-sm font-medium">{{ t('band_space_finance.common.label') }}</label>
        <InputText id="recurrence-label" v-model="form.label" :placeholder="t('band_space_finance.recurrence_drawer.label_placeholder')" />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-type" class="text-sm font-medium">{{ t('band_space_finance.common.type') }}</label>
        <Select
          id="recurrence-type"
          v-model="form.type"
          :options="typeOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_finance.common.select_type')"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-amount" class="text-sm font-medium">{{ t('band_space_finance.recurrence_drawer.amount') }}</label>
        <InputNumber
          id="recurrence-amount"
          v-model="form.amountEuros"
          :max="MAX_AMOUNT_EUROS"
          :minFractionDigits="2"
          :maxFractionDigits="2"
          suffix=" €"
          placeholder="0,00"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-scope" class="text-sm font-medium">{{ t('band_space_finance.common.scope') }}</label>
        <Select
          id="recurrence-scope"
          v-model="form.scope"
          :options="scopeOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_finance.common.select_scope')"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-interval" class="text-sm font-medium">{{ t('band_space_finance.recurrence_drawer.interval') }}</label>
        <Select
          id="recurrence-interval"
          v-model="form.interval"
          :options="intervalOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_finance.recurrence_drawer.select_interval')"
          :disabled="isEditMode"
        />
        <small v-if="isEditMode" class="text-surface-600 dark:text-surface-300">{{ frozenScheduleHint }}</small>
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-start-date" class="text-sm font-medium">{{ t('band_space_finance.recurrence_drawer.start_date') }} <span class="text-red-500">*</span></label>
        <DatePicker
          id="recurrence-start-date"
          v-model="form.startDate"
          dateFormat="dd/mm/yy"
          showIcon
          :disabled="isEditMode"
        />
        <small v-if="isEditMode" class="text-surface-600 dark:text-surface-300">{{ frozenScheduleHint }}</small>
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-end-date" class="text-sm font-medium">{{ t('band_space_finance.recurrence_drawer.end_date') }} <span class="text-red-500">*</span></label>
        <DatePicker id="recurrence-end-date" v-model="form.endDate" dateFormat="dd/mm/yy" showIcon />
      </div>

      <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-4">
        <Button
          type="submit"
          :label="t('band_space_finance.common.save')"
          :loading="financeStore.isCreating || financeStore.isSaving"
        />
        <Button
          type="button"
          :label="t('band_space_finance.common.cancel')"
          severity="secondary"
          text
          @click="visibleModel = false"
        />
        <div v-if="isEditMode" class="flex items-center gap-2 ml-auto">
          <Button
            type="button"
            :label="props.recurrence.is_active ? t('band_space_finance.recurrence_drawer.deactivate') : t('band_space_finance.recurrence_drawer.activate')"
            :severity="props.recurrence.is_active ? 'warn' : 'success'"
            text
            @click="handleToggleActive"
          />
          <Button
            type="button"
            :label="t('band_space_finance.common.delete')"
            severity="danger"
            text
            :loading="financeStore.isDeleting"
            @click="handleDelete"
          />
        </div>
      </div>
    </form>
  </Drawer>
</template>

<script setup>
import { format } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { apiErrorDetail } from '../../../api/utils/apiErrorDetail.js'
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'
import { centsToCurrency, currencyToCents, MAX_AMOUNT_EUROS } from '../../../utils/currency.js'
import { recurrenceDeleteMessage } from '../../../utils/financeConfirmations.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
  recurrence: { type: Object, default: null },
  bandSpaceId: { type: String, required: true },
  categories: { type: Array, required: true }
})

const emit = defineEmits(['update:visible', 'saved', 'deleted'])
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const financeStore = useBandSpaceFinanceStore()

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const isEditMode = computed(() => props.recurrence !== null)

// The generated entries all sit on the grid drawn by the start date and the interval, so the API refuses
// to re-anchor it once entries exist. Say it here rather than let the user hit a 422.
const frozenScheduleHint = computed(() =>
  t('band_space_finance.recurrence_drawer.frozen_schedule_hint')
)

const typeOptions = computed(() => [
  { label: t('band_space_finance.type.expense'), value: 'expense' },
  { label: t('band_space_finance.type.income'), value: 'income' }
])

const scopeOptions = computed(() => [
  { label: t('band_space_finance.scope.band'), value: 'band' },
  { label: t('band_space_finance.scope.personal'), value: 'personal' }
])

const intervalOptions = computed(() => [
  { label: t('band_space_finance.recurrence_drawer.intervals.weekly'), value: 'weekly' },
  { label: t('band_space_finance.recurrence_drawer.intervals.monthly'), value: 'monthly' },
  { label: t('band_space_finance.recurrence_drawer.intervals.quarterly'), value: 'quarterly' },
  { label: t('band_space_finance.recurrence_drawer.intervals.yearly'), value: 'yearly' }
])

const form = reactive({
  categoryId: null,
  label: '',
  type: 'expense',
  amountEuros: null,
  scope: 'band',
  interval: 'monthly',
  startDate: null,
  endDate: null
})

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    if (props.recurrence) {
      form.categoryId = props.recurrence.category_id ?? null
      form.label = props.recurrence.label ?? ''
      form.type = props.recurrence.type ?? 'expense'
      form.amountEuros =
        props.recurrence.amount != null ? centsToCurrency(props.recurrence.amount) : null
      form.scope = props.recurrence.scope ?? 'band'
      form.interval = props.recurrence.interval ?? 'monthly'
      form.startDate = props.recurrence.start_date ? new Date(props.recurrence.start_date) : null
      form.endDate = props.recurrence.end_date ? new Date(props.recurrence.end_date) : null
    } else {
      form.categoryId = null
      form.label = ''
      form.type = 'expense'
      form.amountEuros = null
      form.scope = 'band'
      form.interval = 'monthly'
      form.startDate = null
      form.endDate = null
    }
  }
)

function buildPayload() {
  const data = {
    label: form.label,
    type: form.type,
    scope: form.scope,
    amount: form.amountEuros != null ? currencyToCents(form.amountEuros) : null,
    end_date: form.endDate ? format(form.endDate, 'yyyy-MM-dd') : null
  }

  // The schedule is set once, at creation: sending it on an edit would only be rejected.
  if (!isEditMode.value) {
    data.category_id = form.categoryId
    data.interval = form.interval
    data.start_date = form.startDate ? format(form.startDate, 'yyyy-MM-dd') : null
  }

  return data
}

async function handleSave() {
  try {
    const data = buildPayload()

    if (isEditMode.value) {
      const result = await financeStore.updateRecurrence(
        props.bandSpaceId,
        props.recurrence.id,
        data
      )
      emit('saved', result)
      return
    }

    await financeStore.createRecurrence(props.bandSpaceId, data)
    emit('saved')
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_finance.common.error'),
      detail: apiErrorDetail(error, t('band_space_finance.recurrence_drawer.save_error')),
      life: 5000
    })
  }
}

function handleDelete() {
  confirm.require({
    message: recurrenceDeleteMessage(),
    header: t('band_space_finance.common.confirm_delete_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space_finance.common.cancel'),
    acceptLabel: t('band_space_finance.common.delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await financeStore.deleteRecurrence(props.bandSpaceId, props.recurrence.id)
        emit('deleted')
      } catch (error) {
        // A personal recurrence answers only to its owner, and that 403 says so.
        toast.add({
          severity: 'error',
          summary: t('band_space_finance.common.error'),
          detail: apiErrorDetail(error, t('band_space_finance.recurrence_drawer.delete_error')),
          life: 5000
        })
      }
    }
  })
}

async function handleToggleActive() {
  try {
    const result = await financeStore.updateRecurrence(props.bandSpaceId, props.recurrence.id, {
      is_active: !props.recurrence.is_active
    })
    emit('saved', result)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_finance.common.error'),
      detail: apiErrorDetail(error, t('band_space_finance.recurrence_drawer.toggle_error')),
      life: 5000
    })
  }
}
</script>
