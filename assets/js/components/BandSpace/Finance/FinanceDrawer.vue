<template>
  <Drawer
    v-model:visible="visibleModel"
    position="right"
    :header="isEditMode ? 'Modifier l\u2019entrée' : 'Nouvelle entrée'"
    class="w-full md:w-[28rem]"
  >
    <form class="flex flex-col gap-4" @submit.prevent="handleSave">
      <Message v-if="formError" severity="error" :closable="true" @close="formError = null" class="text-sm">
        {{ formError }}
      </Message>

      <div class="flex flex-col gap-1">
        <label for="finance-label" class="text-sm font-medium">Libellé</label>
        <InputText id="finance-label" v-model="form.label" placeholder="Ex : Location salle de répétition" />
      </div>

      <div v-if="showCategorySelect" class="flex flex-col gap-1">
        <label for="finance-category" class="text-sm font-medium">Catégorie <span class="text-red-500">*</span></label>
        <Select
          id="finance-category"
          v-model="form.categoryId"
          :options="financeStore.categories"
          optionLabel="name"
          optionValue="id"
          placeholder="Sélectionne une catégorie"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="finance-type" class="text-sm font-medium">Type</label>
        <Select
          id="finance-type"
          v-model="form.type"
          :options="typeOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionne un type"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm font-medium">Statut</label>
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
          v-model="form.status"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionne un statut"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm font-medium">Montant</label>
        <SelectButton
          v-model="form.amountMode"
          :options="amountModeOptions"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          class="text-sm"
        />
        <div v-if="form.amountMode === 'exact'" class="flex flex-col gap-1">
          <InputNumber
            id="finance-amount"
            v-model="form.amountEuros"
            :minFractionDigits="2"
            :maxFractionDigits="2"
            suffix=" €"
            placeholder="0,00"
          />
        </div>
        <div v-else class="flex flex-col sm:flex-row gap-2">
          <InputNumber
            v-model="form.amountMinEuros"
            :minFractionDigits="2"
            :maxFractionDigits="2"
            suffix=" €"
            placeholder="Min"
            class="flex-1"
          />
          <InputNumber
            v-model="form.amountMaxEuros"
            :minFractionDigits="2"
            :maxFractionDigits="2"
            suffix=" €"
            placeholder="Max"
            class="flex-1"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label for="finance-date" class="text-sm font-medium">Date <span class="text-red-500">*</span></label>
        <DatePicker id="finance-date" v-model="form.date" dateFormat="dd/mm/yy" showIcon />
      </div>

      <div class="flex flex-col gap-1">
        <label for="finance-scope" class="text-sm font-medium">Portée</label>
        <Select
          id="finance-scope"
          v-model="form.scope"
          :options="scopeOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionne la portée"
        />
      </div>

      <div v-if="isEditMode && props.entry?.recurrence_id" class="flex items-center gap-2 text-sm text-surface-500 bg-surface-100 dark:bg-surface-800 rounded-lg px-3 py-2">
        <i class="pi pi-sync text-xs"></i>
        <span>Entrée générée par une récurrence</span>
      </div>

      <SplitManager
        v-if="form.scope === 'band'"
        ref="splitManagerRef"
        :bandSpaceId="props.bandSpaceId"
        :entryId="isEditMode ? props.entry.id : null"
        :amountEuros="effectiveAmountEuros"
        :visible="props.visible"
      />

      <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-4">
        <Button
          v-if="canEditEntry"
          type="submit"
          label="Enregistrer"
          :loading="financeStore.isCreating || financeStore.isSaving"
        />
        <Button
          type="button"
          label="Annuler"
          severity="secondary"
          text
          @click="visibleModel = false"
        />
        <Button
          v-if="isEditMode && canEditEntry"
          type="button"
          label="Supprimer"
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
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Drawer from 'primevue/drawer'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import { format } from 'date-fns'
import { computed, reactive, ref, watch } from 'vue'
import { centsToCurrency, currencyToCents } from '../../../utils/currency.js'
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'
import SplitManager from './SplitManager.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  entry: { type: Object, default: null },
  categoryId: { type: String, default: null },
  bandSpaceId: { type: String, required: true },
  currentMembershipId: { type: String, default: null }
})

const emit = defineEmits(['update:visible', 'saved', 'deleted'])
const confirm = useConfirm()
const toast = useToast()
const financeStore = useBandSpaceFinanceStore()
const splitManagerRef = ref(null)
const formError = ref(null)

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

const typeOptions = [
  { label: 'Dépense', value: 'expense' },
  { label: 'Revenu', value: 'income' }
]

const transitionMap = {
  planned: [
    { value: 'committed', label: 'Marquer comme Engagé', severity: 'warn' },
    { value: 'paid', label: 'Marquer comme Payé', severity: 'success' }
  ],
  committed: [
    { value: 'paid', label: 'Marquer comme Payé', severity: 'success' },
    { value: 'planned', label: 'Annuler → Prévu', severity: 'secondary' }
  ],
  paid: [
    { value: 'committed', label: 'Rouvrir → Engagé', severity: 'warn' }
  ]
}

const availableTransitions = computed(() => transitionMap[form.status] ?? [])

const statusOptions = [
  { label: 'Prévu', value: 'planned' },
  { label: 'Engagé', value: 'committed' },
  { label: 'Payé', value: 'paid' }
]

const showCategorySelect = computed(() => !isEditMode.value && !props.categoryId)

const scopeOptions = [
  { label: 'Groupe', value: 'band' },
  { label: 'Personnel', value: 'personal' }
]

const amountModeOptions = [
  { label: 'Montant exact', value: 'exact' },
  { label: 'Fourchette', value: 'range' }
]

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

watch(() => form.amountMode, (newMode, oldMode) => {
  if (oldMode === 'exact') {
    form.amountEuros = null
  } else {
    form.amountMinEuros = null
    form.amountMaxEuros = null
  }
})

watch(
  () => props.visible,
  async (visible) => {
    if (!visible) return
    formError.value = null

    if (props.entry) {
      form.label = props.entry.label ?? ''
      form.type = props.entry.type ?? 'expense'
      form.status = props.entry.status ?? 'planned'
      if (props.entry.amount_min != null || props.entry.amount_max != null) {
        form.amountMode = 'range'
        form.amountEuros = null
        form.amountMinEuros = props.entry.amount_min != null ? centsToCurrency(props.entry.amount_min) : null
        form.amountMaxEuros = props.entry.amount_max != null ? centsToCurrency(props.entry.amount_max) : null
      } else {
        form.amountMode = 'exact'
        form.amountEuros = props.entry.amount != null ? centsToCurrency(props.entry.amount) : null
        form.amountMinEuros = null
        form.amountMaxEuros = null
      }
      form.date = new Date(props.entry.date)
      form.scope = props.entry.scope ?? 'band'
      form.categoryId = props.entry.category_id ?? null

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

      await splitManagerRef.value?.reset(null)
    }
  }
)

function statusLabel(status) {
  const labels = { planned: 'Prévu', committed: 'Engagé', paid: 'Payé' }
  return labels[status] ?? status
}

function statusBadgeClass(status) {
  switch (status) {
    case 'paid':
      return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'committed':
      return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
    case 'planned':
    default:
      return 'bg-surface-200 text-surface-600 dark:bg-surface-700 dark:text-surface-300'
  }
}

async function handleStatusTransition(newStatus) {
  formError.value = null
  try {
    await financeStore.updateEntry(props.bandSpaceId, props.entry.id, { status: newStatus })
    trackUmamiEvent('finance-entry-status-change', { from: form.status, to: newStatus })
    form.status = newStatus
  } catch (error) {
    formError.value = error.message || 'Impossible de changer le statut'
  }
}

function buildPayload() {
  const data = {
    label: form.label,
    type: form.type,
    status: form.status,
    amount: form.amountMode === 'exact' && form.amountEuros != null ? currencyToCents(form.amountEuros) : null,
    amount_min: form.amountMode === 'range' && form.amountMinEuros != null ? currencyToCents(form.amountMinEuros) : null,
    amount_max: form.amountMode === 'range' && form.amountMaxEuros != null ? currencyToCents(form.amountMaxEuros) : null,
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

    const splitManager = splitManagerRef.value
    if (entryId && splitManager) {
      try {
        if (splitManager.activeSplitsCount > 0) {
          await splitManager.syncSplits(entryId)
        } else if (splitManager.existingSplits.length > 0) {
          await splitManager.syncSplits(entryId)
        }
      } catch {
        toast.add({ severity: 'warn', summary: 'Attention', detail: 'L\u2019entrée a été enregistrée mais la répartition a échoué', life: 5000 })
      }
    }

    emit('saved')
  } catch (error) {
    formError.value = error.message || 'Impossible d\u2019enregistrer l\u2019entrée'
  }
}

function handleDelete() {
  confirm.require({
    message: 'Es-tu sûr de vouloir supprimer cette entrée ?',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await financeStore.deleteEntry(props.bandSpaceId, props.entry.id)
        emit('deleted')
      } catch (error) {
        formError.value = error.message || 'Impossible de supprimer l\u2019entrée'
      }
    }
  })
}
</script>
