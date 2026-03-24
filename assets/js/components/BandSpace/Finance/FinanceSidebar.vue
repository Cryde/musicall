<template>
  <div class="flex flex-col gap-6">
    <!-- Contributions des membres -->
    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-3">Contributions des membres</h3>

      <div v-if="!memberContributions.length" class="text-sm text-surface-400 italic">
        Aucune contribution
      </div>

      <div v-else class="flex flex-col gap-3">
        <div v-for="member in memberContributions" :key="member.member_id">
          <div class="flex items-center justify-between text-sm mb-1">
            <span class="text-surface-700 dark:text-surface-300">{{ member.name }}</span>
            <span class="font-medium tabular-nums">{{ formatAmount(member.total) }}</span>
          </div>
          <div class="h-1.5 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden">
            <div
              class="h-full rounded-full bg-primary transition-all"
              :style="{ width: contributionPercent(member.total) + '%' }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Prochaines échéances -->
    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-3">Prochaines échéances</h3>

      <div v-if="!upcomingEntries.length" class="text-sm text-surface-400 italic">
        Aucune échéance à venir
      </div>

      <div v-else class="flex flex-col gap-2">
        <div
          v-for="entry in upcomingEntries"
          :key="entry.id"
          class="flex items-center gap-2 text-sm"
        >
          <span
            class="w-2 h-2 rounded-full flex-shrink-0"
            :class="statusDotClass(entry.status)"
          ></span>
          <span class="flex-1 truncate">{{ entry.label }}</span>
          <span class="text-surface-400 text-xs flex-shrink-0">{{ formatDateCompact(entry.date) }}</span>
          <span class="font-medium tabular-nums flex-shrink-0">{{ formatEntryAmount(entry) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatAmount } from '../../../utils/currency.js'
import { formatDateCompact } from '../../../utils/date.js'

const props = defineProps({
  summary: { type: Object, default: null }
})

const memberContributions = computed(() => props.summary?.member_contributions ?? [])
const upcomingEntries = computed(() => props.summary?.upcoming_entries ?? [])

const maxContribution = computed(() => {
  if (!memberContributions.value.length) return 0
  return Math.max(...memberContributions.value.map((m) => m.amount))
})

function contributionPercent(amount) {
  if (maxContribution.value === 0) return 0
  return Math.round((amount / maxContribution.value) * 100)
}

function formatEntryAmount(entry) {
  if (entry.amount != null) return formatAmount(entry.amount)
  if (entry.amount_min != null && entry.amount_max != null) {
    return formatAmount(entry.amount_min) + ' - ' + formatAmount(entry.amount_max)
  }
  return formatAmount(0)
}

function statusDotClass(status) {
  switch (status) {
    case 'paid':
      return 'bg-green-500'
    case 'committed':
      return 'bg-orange-500'
    case 'planned':
    default:
      return 'bg-surface-400'
  }
}
</script>
