<template>
  <div class="border border-surface-200 dark:border-surface-700 rounded-lg overflow-hidden">
    <button
      type="button"
      class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors"
      @click="expanded = !expanded"
    >
      <span>Répartition entre membres</span>
      <div class="flex items-center gap-2">
        <span class="text-xs text-surface-500">{{ splitsSummaryText }}</span>
        <i
          class="pi pi-chevron-down text-xs text-surface-400 transition-transform duration-200"
          :class="{ 'rotate-180': expanded }"
        ></i>
      </div>
    </button>

    <div v-if="expanded" class="px-3 pb-3 border-t border-surface-200 dark:border-surface-700">
      <div v-if="isLoadingMembers" class="flex justify-center py-4">
        <ProgressSpinner style="width: 1.5rem; height: 1.5rem;" />
      </div>

      <div v-else-if="members.length === 0" class="text-sm text-surface-500 py-3">
        Aucun membre trouvé
      </div>

      <div v-else class="flex flex-col gap-2 pt-3">
        <div
          v-for="member in members"
          :key="member.id"
          class="flex items-center gap-3"
        >
          <div
            class="w-7 h-7 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xs font-semibold flex-shrink-0"
          >
            {{ memberInitial(member.username) }}
          </div>
          <span class="text-sm flex-1 truncate">{{ member.username }}</span>
          <InputNumber
            v-model="splitAmounts[member.id]"
            :minFractionDigits="2"
            :maxFractionDigits="2"
            suffix=" €"
            placeholder="0,00"
            class="w-20 sm:w-28"
            size="small"
            :disabled="props.disabled"
          />
        </div>

        <!-- Total line -->
        <div class="border-t border-surface-200 dark:border-surface-700 mt-2 pt-2 flex items-center justify-between text-sm">
          <span class="text-surface-500">Total</span>
          <span
            :class="splitsMatchAmount ? 'text-green-500' : 'text-orange-500'"
            class="font-medium"
          >
            {{ formatAmount(splitsTotal) }}
            <span v-if="amountEuros != null">
              / {{ formatAmount(Math.round(amountEuros * 100)) }}
            </span>
            <i
              :class="splitsMatchAmount ? 'pi pi-check' : 'pi pi-exclamation-triangle'"
              class="text-xs ml-1"
            ></i>
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import InputNumber from 'primevue/inputnumber'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, reactive, ref, watch } from 'vue'
import bandSpaceFinanceApi from '../../../api/bandSpace/band-space-finance.js'
import bandSpaceSettingsApi from '../../../api/bandSpace/band-space-settings.js'
import { centsToCurrency, currencyToCents, formatAmount } from '../../../utils/currency.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  entryId: { type: String, default: null },
  amountEuros: { type: Number, default: null },
  visible: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false }
})

const expanded = ref(false)
const members = ref([])
const isLoadingMembers = ref(false)
const splitAmounts = reactive({})
const existingSplits = ref([])

const splitsTotal = computed(() => {
  let total = 0
  for (const memberId in splitAmounts) {
    const val = splitAmounts[memberId]
    if (val != null && val > 0) {
      total += currencyToCents(val)
    }
  }
  return total
})

const splitsMatchAmount = computed(() => {
  if (props.amountEuros == null) return splitsTotal.value === 0
  return splitsTotal.value === currencyToCents(props.amountEuros)
})

const activeSplitsCount = computed(() => {
  let count = 0
  for (const memberId in splitAmounts) {
    if (splitAmounts[memberId] != null && splitAmounts[memberId] > 0) {
      count++
    }
  }
  return count
})

const splitsSummaryText = computed(() => {
  if (activeSplitsCount.value === 0) return 'Aucune répartition'
  return `${activeSplitsCount.value} membre${activeSplitsCount.value > 1 ? 's' : ''} · ${formatAmount(splitsTotal.value)}`
})

function memberInitial(username) {
  return username ? username.charAt(0).toUpperCase() : '?'
}

async function loadMembers() {
  if (members.value.length > 0) return
  isLoadingMembers.value = true
  try {
    members.value = await bandSpaceSettingsApi.getMembers(props.bandSpaceId)
  } catch {
    members.value = []
  } finally {
    isLoadingMembers.value = false
  }
}

async function loadExistingSplits() {
  if (!props.entryId) return
  try {
    existingSplits.value = await bandSpaceFinanceApi.getSplits(props.bandSpaceId, props.entryId)
  } catch {
    existingSplits.value = []
  }
}

function resetSplitAmounts() {
  for (const key in splitAmounts) {
    delete splitAmounts[key]
  }
}

function prefillSplitsFromExisting() {
  for (const split of existingSplits.value) {
    splitAmounts[split.member_id] = centsToCurrency(split.amount)
  }
}

function buildSplitsPayload() {
  const splits = []
  for (const memberId in splitAmounts) {
    const val = splitAmounts[memberId]
    if (val != null && val > 0) {
      splits.push({
        member_id: memberId,
        amount: currencyToCents(val)
      })
    }
  }
  return splits
}

async function syncSplits(entryId) {
  const desired = buildSplitsPayload()
  const desiredByMember = new Map(desired.map((s) => [s.member_id, s.amount]))

  for (const existing of existingSplits.value) {
    const newAmount = desiredByMember.get(existing.member_id)
    if (newAmount == null || newAmount !== existing.amount) {
      await bandSpaceFinanceApi.deleteSplit(props.bandSpaceId, entryId, existing.id)
    }
  }

  const existingByMember = new Map(existingSplits.value.map((s) => [s.member_id, s.amount]))
  for (const split of desired) {
    const oldAmount = existingByMember.get(split.member_id)
    if (oldAmount == null || oldAmount !== split.amount) {
      await bandSpaceFinanceApi.createSplit(props.bandSpaceId, entryId, split)
    }
  }
}

async function reset(entryId) {
  expanded.value = false
  resetSplitAmounts()
  existingSplits.value = []

  if (entryId) {
    await Promise.all([loadMembers(), loadExistingSplits()])
    prefillSplitsFromExisting()
    if (existingSplits.value.length > 0) {
      expanded.value = true
    }
  }
}

watch(expanded, (isExpanded) => {
  if (isExpanded) {
    loadMembers()
  }
})

defineExpose({
  syncSplits,
  activeSplitsCount,
  existingSplits,
  reset
})
</script>
