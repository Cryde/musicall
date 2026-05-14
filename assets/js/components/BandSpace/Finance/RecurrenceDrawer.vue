<template>
  <Drawer
    v-model:visible="visibleModel"
    position="right"
    :header="isEditMode ? 'Modifier la récurrence' : 'Nouvelle récurrence'"
    class="w-full md:w-[28rem]"
  >
    <form class="flex flex-col gap-4" @submit.prevent="handleSave">
      <div v-if="!isEditMode" class="flex flex-col gap-1">
        <label for="recurrence-category" class="text-sm font-medium">Catégorie</label>
        <Select
          id="recurrence-category"
          v-model="form.categoryId"
          :options="props.categories"
          optionLabel="name"
          optionValue="id"
          placeholder="Sélectionne une catégorie"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-label" class="text-sm font-medium">Libellé</label>
        <InputText id="recurrence-label" v-model="form.label" placeholder="Ex : Loyer local de répétition" />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-type" class="text-sm font-medium">Type</label>
        <Select
          id="recurrence-type"
          v-model="form.type"
          :options="typeOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionne un type"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-amount" class="text-sm font-medium">Montant (en euros)</label>
        <InputNumber
          id="recurrence-amount"
          v-model="form.amountEuros"
          :minFractionDigits="2"
          :maxFractionDigits="2"
          suffix=" €"
          placeholder="0,00"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-scope" class="text-sm font-medium">Portée</label>
        <Select
          id="recurrence-scope"
          v-model="form.scope"
          :options="scopeOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionne la portée"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-interval" class="text-sm font-medium">Intervalle</label>
        <Select
          id="recurrence-interval"
          v-model="form.interval"
          :options="intervalOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionne un intervalle"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-start-date" class="text-sm font-medium">Date de début <span class="text-red-500">*</span></label>
        <DatePicker id="recurrence-start-date" v-model="form.startDate" dateFormat="dd/mm/yy" showIcon />
      </div>

      <div class="flex flex-col gap-1">
        <label for="recurrence-end-date" class="text-sm font-medium">Date de fin <span class="text-red-500">*</span></label>
        <DatePicker id="recurrence-end-date" v-model="form.endDate" dateFormat="dd/mm/yy" showIcon />
      </div>

      <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-4">
        <Button
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
        <div v-if="isEditMode" class="flex items-center gap-2 ml-auto">
          <Button
            type="button"
            :label="props.recurrence.is_active ? 'Désactiver' : 'Activer'"
            :severity="props.recurrence.is_active ? 'warn' : 'success'"
            text
            @click="handleToggleActive"
          />
          <Button
            type="button"
            label="Supprimer"
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
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'
import { centsToCurrency, currencyToCents } from '../../../utils/currency.js'

const props = defineProps({
  visible: { type: Boolean, default: false },
  recurrence: { type: Object, default: null },
  bandSpaceId: { type: String, required: true },
  categories: { type: Array, required: true }
})

const emit = defineEmits(['update:visible', 'saved', 'deleted'])
const confirm = useConfirm()
const toast = useToast()
const financeStore = useBandSpaceFinanceStore()

const visibleModel = computed({
  get: () => props.visible,
  set: (val) => emit('update:visible', val)
})

const isEditMode = computed(() => props.recurrence !== null)

const typeOptions = [
  { label: 'Dépense', value: 'expense' },
  { label: 'Revenu', value: 'income' }
]

const scopeOptions = [
  { label: 'Groupe', value: 'band' },
  { label: 'Personnel', value: 'personal' }
]

const intervalOptions = [
  { label: 'Hebdomadaire', value: 'weekly' },
  { label: 'Mensuel', value: 'monthly' },
  { label: 'Trimestriel', value: 'quarterly' },
  { label: 'Annuel', value: 'yearly' }
]

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
    interval: form.interval,
    amount: form.amountEuros != null ? currencyToCents(form.amountEuros) : null,
    start_date: form.startDate ? format(form.startDate, 'yyyy-MM-dd') : null,
    end_date: form.endDate ? format(form.endDate, 'yyyy-MM-dd') : null
  }

  if (!isEditMode.value) {
    data.category_id = form.categoryId
  }

  return data
}

async function handleSave() {
  try {
    const data = buildPayload()

    if (isEditMode.value) {
      await financeStore.updateRecurrence(props.bandSpaceId, props.recurrence.id, data)
    } else {
      await financeStore.createRecurrence(props.bandSpaceId, data)
    }

    emit('saved')
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible d\u2019enregistrer la récurrence',
      life: 5000
    })
  }
}

function handleDelete() {
  confirm.require({
    message: 'Es-tu sûr de vouloir supprimer cette récurrence ?',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await financeStore.deleteRecurrence(props.bandSpaceId, props.recurrence.id)
        emit('deleted')
      } catch {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer la récurrence',
          life: 5000
        })
      }
    }
  })
}

async function handleToggleActive() {
  try {
    await financeStore.updateRecurrence(props.bandSpaceId, props.recurrence.id, {
      is_active: !props.recurrence.is_active
    })
    emit('saved')
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de modifier le statut de la récurrence',
      life: 5000
    })
  }
}
</script>
