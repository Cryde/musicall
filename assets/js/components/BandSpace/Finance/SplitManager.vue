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
          <!-- 700 in light mode: the 500 level of both colours fails AA as text. The icon carries an
               accessible name of its own, so the mismatch is stated and not only coloured, and it says
               the same thing as the badge the row shows for the same entry. -->
          <span
            :class="
              splitsMatchAmount
                ? 'text-green-700 dark:text-green-400'
                : 'text-amber-700 dark:text-amber-400'
            "
            class="font-medium"
          >
            {{ formatAmount(splitsTotal) }}
            <span v-if="amountEuros != null">
              / {{ formatAmount(Math.round(amountEuros * 100)) }}
            </span>
            <span role="img" :aria-label="totalMatchLabel">
              <i
                :class="splitsMatchAmount ? 'pi pi-check' : 'pi pi-exclamation-triangle'"
                class="text-xs ml-1"
                aria-hidden="true"
              ></i>
            </span>
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
import { planSplitSync } from '../../../utils/splitReconciliation.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  entryId: { type: String, default: null },
  // What the total line is measured against: the middle of the fourchette when the entry has one.
  amountEuros: { type: Number, default: null },
  // What the API caps the split total at, which is the entry's exact amount and nothing else: an entry
  // storing a fourchette has amount NULL and is capped at nothing. Only ever the exact one, so the
  // guard below refuses exactly what the API would refuse, no more.
  exactAmountEuros: { type: Number, default: null },
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

const totalMatchLabel = computed(() =>
  splitsMatchAmount.value
    ? 'Le total réparti correspond au montant de l’entrée'
    : 'Le total réparti ne correspond pas au montant de l’entrée'
)

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

async function loadExistingSplits(entryId) {
  // Use the entryId passed to reset(), NOT props.entryId: when the drawer opens an
  // existing entry, props.entryId may not have propagated yet, which would make this
  // bail out and leave the splits empty (they exist, just never loaded).
  if (!entryId) return
  try {
    existingSplits.value = await bandSpaceFinanceApi.getSplits(props.bandSpaceId, entryId)
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

function buildPlan() {
  const capCents = props.exactAmountEuros != null ? currencyToCents(props.exactAmountEuros) : null

  return planSplitSync(existingSplits.value, buildSplitsPayload(), capCents)
}

/**
 * Why the repartition cannot be saved as typed, or null when it can. The drawer asks before it writes
 * anything at all, so a repartition the API would refuse never costs an existing split.
 */
function validateSplits() {
  return buildPlan().error
}

/**
 * @returns {Promise<boolean>} whether a split was actually written, which the caller needs because
 *          the répartition is saved after the entry: the lists it reloaded before this ran describe
 *          the previous shares, so they have to be reloaded again, but only when there is a change.
 */
async function syncSplits(entryId) {
  const { error, operations } = buildPlan()
  if (error) {
    throw new Error(error)
  }
  if (operations.length === 0) {
    return false
  }

  for (const operation of operations) {
    if (operation.type === 'delete') {
      await bandSpaceFinanceApi.deleteSplit(props.bandSpaceId, entryId, operation.splitId)
    } else {
      await bandSpaceFinanceApi.createSplit(props.bandSpaceId, entryId, {
        member_id: operation.memberId,
        amount: operation.amount
      })
    }
  }

  // The plan was built against the splits loaded when the drawer opened. Saving twice without
  // re-reading them would plan the second save against split ids that no longer exist.
  await loadExistingSplits(entryId)

  return true
}

async function reset(entryId) {
  expanded.value = false
  resetSplitAmounts()
  existingSplits.value = []

  if (entryId) {
    await Promise.all([loadMembers(), loadExistingSplits(entryId)])
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
  validateSplits,
  syncSplits,
  loadExistingSplits,
  reset
})
</script>
