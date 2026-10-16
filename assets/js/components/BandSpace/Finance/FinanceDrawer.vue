<template>
  <Drawer
    v-model:visible="visibleModel"
    position="right"
    :header="isEditMode ? t('band_space_finance.entry_drawer.title_edit') : t('band_space_finance.entry_drawer.title_new')"
    class="w-full! md:w-[28rem]!"
  >
    <form class="flex flex-col gap-4" @submit.prevent="handleSave">
      <Message v-if="formError" severity="error" :closable="true" @close="formError = null" class="text-sm">
        {{ formError }}
      </Message>

      <Message v-if="isLocked" severity="info" :closable="false" class="text-sm">
        {{ t('band_space_finance.entry_drawer.locked') }}
      </Message>

      <div class="flex flex-col gap-1">
        <label for="finance-label" class="text-sm font-medium">{{ t('band_space_finance.common.label') }} <span class="text-red-500">*</span></label>
        <InputText id="finance-label" v-model="form.label" :disabled="isLocked" :placeholder="t('band_space_finance.entry_drawer.label_placeholder')" />
      </div>

      <div v-if="showCategorySelect" class="flex flex-col gap-1">
        <label for="finance-category" class="text-sm font-medium">{{ t('band_space_finance.common.category') }} <span class="text-red-500">*</span></label>
        <Select
          id="finance-category"
          v-model="form.categoryId"
          :options="financeStore.categories"
          optionLabel="name"
          optionValue="id"
          :placeholder="t('band_space_finance.common.select_category')"
          :disabled="isLocked"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="finance-type" class="text-sm font-medium">{{ t('band_space_finance.common.type') }}</label>
        <Select
          id="finance-type"
          v-model="form.type"
          :options="typeOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_finance.common.select_type')"
          :disabled="isLocked"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm font-medium">{{ t('band_space_finance.entry_drawer.status') }}</label>
        <div v-if="isEditMode" class="flex items-center gap-2 flex-wrap">
          <span
            class="text-xs font-semibold px-2.5 py-1 rounded-full"
            :class="statusBadgeClass(form.status)"
          >
            {{ statusLabel(form.status) }}
          </span>
          <template v-if="canEditEntry">
            <Button
              v-for="transition in availableTransitions"
              :key="transition.value"
              :label="transition.label"
              :severity="transition.severity"
              size="small"
              text
              @click="handleStatusTransition(transition.value)"
            />
          </template>
        </div>
        <Select
          v-else
          id="finance-status"
          :aria-label="t('band_space_finance.entry_drawer.status')"
          v-model="form.status"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_finance.entry_drawer.select_status')"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm font-medium">{{ t('band_space_finance.entry_drawer.amount') }}</label>
        <SelectButton
          v-model="form.amountMode"
          :aria-label="t('band_space_finance.entry_drawer.amount')"
          :options="amountModeOptions"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          class="text-sm"
          :disabled="isLocked"
        />
        <div v-if="form.amountMode === 'exact'" class="flex flex-col gap-1">
          <InputNumber
            id="finance-amount"
            :aria-label="t('band_space_finance.entry_drawer.amount')"
            v-model="form.amountEuros"
            :max="MAX_AMOUNT_EUROS"
            :minFractionDigits="2"
            :maxFractionDigits="2"
            suffix=" €"
            placeholder="0,00"
            :disabled="isLocked"
          />
        </div>
        <div v-else class="flex flex-col sm:flex-row gap-2">
          <InputNumber
            v-model="form.amountMinEuros"
            :max="MAX_AMOUNT_EUROS"
            :aria-label="t('band_space_finance.entry_drawer.amount_min')"
            :minFractionDigits="2"
            :maxFractionDigits="2"
            suffix=" €"
            :placeholder="t('band_space_finance.entry_drawer.amount_min_placeholder')"
            fluid
            class="flex-1 min-w-0"
            :disabled="isLocked"
          />
          <InputNumber
            v-model="form.amountMaxEuros"
            :max="MAX_AMOUNT_EUROS"
            :aria-label="t('band_space_finance.entry_drawer.amount_max')"
            :minFractionDigits="2"
            :maxFractionDigits="2"
            suffix=" €"
            :placeholder="t('band_space_finance.entry_drawer.amount_max_placeholder')"
            fluid
            class="flex-1 min-w-0"
            :disabled="isLocked"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label for="finance-date" class="text-sm font-medium">{{ t('band_space_finance.entry_drawer.date') }} <span class="text-red-500">*</span></label>
        <DatePicker id="finance-date" v-model="form.date" dateFormat="dd/mm/yy" showIcon :disabled="isLocked" />
      </div>

      <div class="flex flex-col gap-1">
        <label for="finance-scope" class="text-sm font-medium">{{ t('band_space_finance.common.scope') }}</label>
        <Select
          id="finance-scope"
          v-model="form.scope"
          :options="scopeOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('band_space_finance.common.select_scope')"
          :disabled="isLocked"
        />
      </div>

      <div v-if="isEditMode && props.entry?.recurrence_id" class="flex items-center gap-2 text-sm text-surface-500 bg-surface-100 dark:bg-surface-800 rounded-lg px-3 py-2">
        <i class="pi pi-sync text-xs"></i>
        <span>{{ t('band_space_finance.entry_drawer.generated_by_recurrence') }}</span>
      </div>

      <SplitManager
        v-if="form.scope === 'band'"
        ref="splitManagerRef"
        :bandSpaceId="props.bandSpaceId"
        :entryId="isEditMode ? props.entry.id : null"
        :amountEuros="effectiveAmountEuros"
        :exactAmountEuros="form.amountMode === 'exact' ? form.amountEuros : null"
        :visible="props.visible"
        :disabled="isLocked"
      />

      <div v-if="isEditMode && props.entry?.id" class="border-t border-surface-200 dark:border-surface-700 pt-4">
        <!-- Band Space files are shared: only the entry is private, not what is attached to it (#1146). -->
        <Message v-if="form.scope === 'personal'" severity="warn" :closable="false" class="text-sm mb-3">
          {{ t('band_space_finance.entry_drawer.personal_files_warning') }}
        </Message>
        <AttachedFilesSection
          :band-space-id="props.bandSpaceId"
          source-type="finance"
          :source-id="props.entry.id"
          :can-attach="canEditEntry"
          @count="attachedFileCount = $event"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-4">
        <Button
          v-if="canEditEntry && !isLocked"
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
        <Button
          v-if="isEditMode && canEditEntry && !isLocked"
          type="button"
          :label="t('band_space_finance.common.delete')"
          severity="danger"
          text
          class="ml-auto"
          :loading="financeStore.isDeleting"
          @click="handleDelete"
        />
      </div>
    </form>
  </Drawer>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { format } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { useConfirm } from 'primevue/useconfirm'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ERROR_CODES } from '../../../constants/errorCodes.js'
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'
import { attachedFilesNotice } from '../../../utils/attachedFilesNotice.js'
import { centsToCurrency, currencyToCents, MAX_AMOUNT_EUROS } from '../../../utils/currency.js'
import { recordOpenedItem } from '../../../utils/openedItemRecorder.js'
import AttachedFilesSection from '../Files/AttachedFilesSection.vue'
import SplitManager from './SplitManager.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  entry: { type: Object, default: null },
  categoryId: { type: String, default: null },
  bandSpaceId: { type: String, required: true },
  currentMembershipId: { type: String, default: null }
})

const emit = defineEmits(['update:visible', 'saved', 'deleted'])
const { t } = useI18n()
const confirm = useConfirm()
const financeStore = useBandSpaceFinanceStore()
const splitManagerRef = ref(null)
const formError = ref(null)
const attachedFileCount = ref(0)

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const isEditMode = computed(() => props.entry !== null)
const canEditEntry = computed(() => {
  if (!isEditMode.value) return true
  if (props.entry.scope !== 'personal') return true
  return props.entry.member_id === props.currentMembershipId
})
const isLocked = computed(() => isEditMode.value && form.status === 'paid')

const typeOptions = computed(() => [
  { label: t('band_space_finance.type.expense'), value: 'expense' },
  { label: t('band_space_finance.type.income'), value: 'income' }
])

const transitionMap = {
  planned: [
    {
      value: 'committed',
      labelKey: 'band_space_finance.entry_drawer.transition.mark_committed',
      severity: 'warn'
    },
    {
      value: 'paid',
      labelKey: 'band_space_finance.entry_drawer.transition.mark_paid',
      severity: 'success'
    }
  ],
  committed: [
    {
      value: 'paid',
      labelKey: 'band_space_finance.entry_drawer.transition.mark_paid',
      severity: 'success'
    },
    {
      value: 'planned',
      labelKey: 'band_space_finance.entry_drawer.transition.back_to_planned',
      severity: 'secondary'
    }
  ],
  paid: [
    {
      value: 'committed',
      labelKey: 'band_space_finance.entry_drawer.transition.reopen',
      severity: 'warn'
    }
  ]
}

const availableTransitions = computed(() =>
  (transitionMap[form.status] ?? []).map(({ labelKey, ...transition }) => ({
    ...transition,
    label: t(labelKey)
  }))
)

const STATUS_LABEL_KEYS = {
  planned: 'band_space_finance.status.planned',
  committed: 'band_space_finance.status.committed',
  paid: 'band_space_finance.status.paid'
}

const statusOptions = computed(() =>
  Object.entries(STATUS_LABEL_KEYS).map(([value, labelKey]) => ({ label: t(labelKey), value }))
)

const showCategorySelect = computed(() => !isEditMode.value && !props.categoryId)

const scopeOptions = computed(() => [
  { label: t('band_space_finance.scope.band'), value: 'band' },
  { label: t('band_space_finance.scope.personal'), value: 'personal' }
])

const amountModeOptions = computed(() => [
  { label: t('band_space_finance.entry_drawer.amount_mode.exact'), value: 'exact' },
  { label: t('band_space_finance.entry_drawer.amount_mode.range'), value: 'range' }
])

const form = reactive({
  label: '',
  categoryId: null,
  type: 'expense',
  status: 'planned',
  amountMode: 'exact',
  amountEuros: null,
  amountMinEuros: null,
  amountMaxEuros: null,
  date: null,
  scope: 'band'
})

const effectiveAmountEuros = computed(() => {
  if (form.amountMode === 'exact') return form.amountEuros
  if (form.amountMinEuros != null && form.amountMaxEuros != null) {
    return (form.amountMinEuros + form.amountMaxEuros) / 2
  }
  return null
})

watch(
  () => form.amountMode,
  (_newMode, oldMode) => {
    if (oldMode === 'exact') {
      form.amountEuros = null
    } else {
      form.amountMinEuros = null
      form.amountMaxEuros = null
    }
  }
)

// The member's « Ouverts par moi » history (#1047). By id, not by object: a live refresh hands over a
// new copy of the same entry, which is not a new open. A null entry is the create form.
watch([() => props.visible, () => props.entry?.id], ([visible, entryId]) => {
  if (visible && entryId) {
    recordOpenedItem(props.bandSpaceId, 'finance', entryId)
  }
})

// Also watch props.entry, not just props.visible: if the drawer ever swaps the entry while
// staying open (today the modal mask prevents it, but that is incidental), we must re-sync the
// form and reload the splits, otherwise entry A's splits would be saved onto entry B.
watch([() => props.visible, () => props.entry], async ([visible]) => {
  if (!visible) return
  formError.value = null

  if (props.entry) {
    form.label = props.entry.label ?? ''
    form.type = props.entry.type ?? 'expense'
    form.status = props.entry.status ?? 'planned'
    if (props.entry.amount_min != null || props.entry.amount_max != null) {
      form.amountMode = 'range'
      form.amountEuros = null
      form.amountMinEuros =
        props.entry.amount_min != null ? centsToCurrency(props.entry.amount_min) : null
      form.amountMaxEuros =
        props.entry.amount_max != null ? centsToCurrency(props.entry.amount_max) : null
    } else {
      form.amountMode = 'exact'
      form.amountEuros = props.entry.amount != null ? centsToCurrency(props.entry.amount) : null
      form.amountMinEuros = null
      form.amountMaxEuros = null
    }
    form.date = new Date(props.entry.date)
    form.scope = props.entry.scope ?? 'band'
    form.categoryId = props.entry.category_id ?? null

    // Wait for the conditionally-rendered (v-if scope==='band') SplitManager to
    // mount, otherwise splitManagerRef.value is still null here and reset() -
    // which loads the existing splits - is silently skipped.
    await nextTick()
    await splitManagerRef.value?.reset(props.entry.id)
  } else {
    form.label = ''
    form.categoryId = null
    form.type = 'expense'
    form.status = 'planned'
    form.amountMode = 'exact'
    form.amountEuros = null
    form.amountMinEuros = null
    form.amountMaxEuros = null
    form.date = new Date()
    form.scope = 'band'

    await nextTick()
    await splitManagerRef.value?.reset(null)
  }
})

function statusLabel(status) {
  const labelKey = STATUS_LABEL_KEYS[status]
  return labelKey ? t(labelKey) : status
}

function statusBadgeClass(status) {
  switch (status) {
    case 'paid':
      return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'committed':
      return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
    default:
      return 'bg-surface-200 text-surface-600 dark:bg-surface-700 dark:text-surface-300'
  }
}

async function applyStatusTransition(newStatus) {
  formError.value = null
  try {
    await financeStore.updateEntry(props.bandSpaceId, props.entry.id, { status: newStatus })
    trackUmamiEvent('finance-entry-status-change', { from: form.status, to: newStatus })
    form.status = newStatus
  } catch (error) {
    formError.value = error.message || t('band_space_finance.entry_drawer.status_change_error')
  }
}

function handleStatusTransition(newStatus) {
  if (form.status === 'paid') {
    confirm.require({
      message: t('band_space_finance.entry_drawer.reopen_confirm'),
      header: t('band_space_finance.entry_drawer.reopen_header'),
      icon: 'pi pi-exclamation-triangle',
      rejectLabel: t('band_space_finance.common.cancel'),
      acceptLabel: t('band_space_finance.entry_drawer.reopen_accept'),
      accept: () => applyStatusTransition(newStatus)
    })
    return
  }
  applyStatusTransition(newStatus)
}

function buildPayload() {
  const data = {
    label: form.label,
    type: form.type,
    status: form.status,
    amount:
      form.amountMode === 'exact' && form.amountEuros != null
        ? currencyToCents(form.amountEuros)
        : null,
    amount_min:
      form.amountMode === 'range' && form.amountMinEuros != null
        ? currencyToCents(form.amountMinEuros)
        : null,
    amount_max:
      form.amountMode === 'range' && form.amountMaxEuros != null
        ? currencyToCents(form.amountMaxEuros)
        : null,
    date: form.date ? format(form.date, 'yyyy-MM-dd') : null,
    scope: form.scope
  }

  if (!isEditMode.value) {
    data.category_id = props.categoryId || form.categoryId
  }

  return data
}

async function handleSave() {
  formError.value = null
  if (!isEditMode.value && !props.categoryId && !form.categoryId) {
    formError.value = t('band_space_finance.entry_drawer.category_required')
    return
  }

  const splitManager = splitManagerRef.value

  // Asked before the entry is written, because raising one member's share means deleting their split
  // and creating it again: a repartition the API would refuse used to cost that member their share.
  const splitError = splitManager?.validateSplits()
  if (splitError) {
    formError.value = splitError
    return
  }

  try {
    const data = buildPayload()
    let entryId

    if (isEditMode.value) {
      await financeStore.updateEntry(props.bandSpaceId, props.entry.id, data)
      entryId = props.entry.id
    } else {
      const created = await financeStore.createEntry(props.bandSpaceId, data)
      entryId = created?.id
    }

    if (entryId && splitManager) {
      try {
        // The répartition is written after the entry, so createEntry/updateEntry reloaded the lists
        // while the old shares were still in place. Without this second reload the row keeps the
        // split_warning badge the member just fixed, and the contributions bar keeps the old totals.
        if (await splitManager.syncSplits(entryId)) {
          await Promise.all([
            financeStore.loadEntries(props.bandSpaceId),
            financeStore.loadSummary(props.bandSpaceId)
          ])
        }
      } catch (error) {
        // The entry itself is saved, so this is not a failed save, but the repartition on screen is no
        // longer what the entry carries. The drawer stays open on the reloaded splits instead of
        // closing on a success message, which is how a lost share used to go unnoticed.
        await splitManager.loadExistingSplits(entryId)
        formError.value = t('band_space_finance.entry_drawer.split_failed', {
          error: error.message || t('band_space_finance.entry_drawer.unknown_error')
        })
        return
      }
    }

    emit('saved')
  } catch (error) {
    await restoreScopeIfSplitsBlockedIt(error)
    formError.value = error.message || t('band_space_finance.entry_drawer.save_error')
  }
}

/**
 * The refusal asks for the répartition to be cleared first, but the SplitManager only renders on a band
 * entry: left on « Personnel », the drawer would show the instruction with nothing to act on. Putting
 * the select back brings the splits into view, already expanded.
 */
async function restoreScopeIfSplitsBlockedIt(error) {
  const blockedBySplits = (error.violationsByField?.scope ?? []).some(
    (violation) => violation.code === ERROR_CODES.PERSONAL_SCOPE_WITH_SPLITS
  )
  if (!blockedBySplits) return

  form.scope = 'band'
  await nextTick()
  await splitManagerRef.value?.reset(props.entry?.id ?? null)
}

function handleDelete() {
  confirm.require({
    message: `${t('band_space_finance.entry_drawer.delete_confirm')}${attachedFilesNotice(attachedFileCount.value)}`,
    header: t('band_space_finance.common.confirm_delete_header'),
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: t('band_space_finance.common.cancel'),
    acceptLabel: t('band_space_finance.common.delete'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await financeStore.deleteEntry(props.bandSpaceId, props.entry.id)
        emit('deleted')
      } catch (error) {
        formError.value = error.message || t('band_space_finance.entry_drawer.delete_error')
      }
    }
  })
}
</script>
