<template>
  <!-- A phone: each block under its own heading, in a box, échéances first since they come next.
       Its own markup rather than classes on the desktop card: the rows differ (a calendar leaf, a
       signed amount, the category), not only their spacing. -->
  <div v-if="isBelowMd" class="flex flex-col gap-5">
    <section>
      <h2 class="mx-1 mb-2 text-[0.9375rem] font-semibold">{{ t('band_space_finance.sidebar.upcoming') }}</h2>
      <div class="overflow-hidden rounded-2xl border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900">
        <p v-if="!upcomingEntries.length" class="px-4 py-3 text-sm text-surface-500 italic dark:text-surface-400">
          {{ t('band_space_finance.sidebar.no_upcoming') }}
        </p>
        <button
          v-for="entry in upcomingEntries"
          :key="entry.id"
          type="button"
          class="flex min-h-15 w-full items-center gap-3 border-surface-200 px-3.5 py-2.5 text-left not-first:border-t dark:border-surface-700"
          @click="emit('open-entry', entry.id)"
        >
          <span class="flex w-8 shrink-0 flex-col items-center leading-tight" aria-hidden="true">
            <span class="text-[0.6875rem] text-primary-700 dark:text-primary-300">{{ formatWeekdayShort(entry.date) }}</span>
            <span class="text-lg font-semibold">{{ formatDayOfMonth(entry.date) }}</span>
          </span>
          <span class="flex min-w-0 flex-1 flex-col gap-0.5">
            <span class="line-clamp-2 text-[0.9375rem] leading-snug font-medium">{{ entry.label }}</span>
            <span class="text-[0.8125rem] text-surface-600 dark:text-surface-400">
              <span class="sr-only">{{ formatDateCompact(entry.date) }} · </span>{{ upcomingMeta(entry) }}
            </span>
          </span>
          <span
            class="shrink-0 text-[0.9375rem] font-semibold tabular-nums"
            :class="entry.type === 'expense' ? 'text-red-700 dark:text-red-400' : 'text-green-700 dark:text-green-400'"
          >
            {{ formatSignedEntryAmount(entry) }}
          </span>
        </button>
      </div>
    </section>

    <section>
      <h2 class="mx-1 mb-2 text-[0.9375rem] font-semibold">{{ t('band_space_finance.sidebar.contributions') }}</h2>
      <div class="overflow-hidden rounded-2xl border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900">
        <p v-if="!memberContributions.length" class="px-4 py-3 text-sm text-surface-500 italic dark:text-surface-400">
          {{ t('band_space_finance.sidebar.no_contribution') }}
        </p>
        <div
          v-for="member in memberContributions"
          :key="member.member_id"
          class="border-surface-200 px-4 py-2.5 not-first:border-t dark:border-surface-700"
        >
          <div class="flex items-center justify-between gap-2 text-sm">
            <span>{{ member.name }}</span>
            <span class="font-semibold tabular-nums">{{ formatAmount(member.total) }}</span>
          </div>
          <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700">
            <div class="h-full rounded-full bg-primary" :style="{ width: barWidth(member.total) + '%' }"></div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <div v-else class="flex flex-col gap-6">
    <!-- Contributions des membres -->
    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-3">{{ t('band_space_finance.sidebar.contributions') }}</h3>

      <div v-if="!memberContributions.length" class="text-sm text-surface-400 italic">
        {{ t('band_space_finance.sidebar.no_contribution') }}
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
              :style="{ width: barWidth(member.total) + '%' }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Prochaines échéances -->
    <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
      <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-200 mb-3">{{ t('band_space_finance.sidebar.upcoming') }}</h3>

      <div v-if="!upcomingEntries.length" class="text-sm text-surface-400 italic">
        {{ t('band_space_finance.sidebar.no_upcoming') }}
      </div>

      <div v-else class="-mx-2 flex flex-col">
        <button
          v-for="entry in upcomingEntries"
          :key="entry.id"
          type="button"
          class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition-colors hover:bg-surface-100 dark:hover:bg-surface-700"
          @click="emit('open-entry', entry.id)"
        >
          <FinanceStatusDot :status="entry.status" />
          <span class="flex-1 truncate">{{ entry.label }}</span>
          <span class="text-surface-400 text-xs flex-shrink-0">{{ formatDateCompact(entry.date) }}</span>
          <span class="font-medium tabular-nums flex-shrink-0">{{ formatEntryAmount(entry) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { useBandSpaceFinanceStore } from '../../../store/bandSpace/bandSpaceFinance.js'
import { contributionPercent, largestContribution } from '../../../utils/contributionBar.js'
import { formatAmount } from '../../../utils/currency.js'
import { formatDateCompact, formatDayOfMonth, formatWeekdayShort } from '../../../utils/date.js'
import { formatEntryAmount, formatSignedEntryAmount } from '../../../utils/financeEntryAmount.js'
import FinanceStatusDot from './FinanceStatusDot.vue'

const { t } = useI18n()

const props = defineProps({
  summary: { type: Object, default: null }
})

const emit = defineEmits(['open-entry'])

const memberContributions = computed(() => props.summary?.member_contributions ?? [])
const upcomingEntries = computed(() => props.summary?.upcoming_entries ?? [])

const maxContribution = computed(() => largestContribution(memberContributions.value))

function barWidth(total) {
  return contributionPercent(total, maxContribution.value)
}

const isBelowMd = useIsBelowMd()
const financeStore = useBandSpaceFinanceStore()

// « Engagé · Live & Concerts »: where the échéance stands, and where it belongs.
function upcomingMeta(upcoming) {
  const status = t(`band_space_finance.status.${upcoming.status}`)
  const category = financeStore.categories.find((item) => item.id === upcoming.category_id)
  return category ? `${status} · ${category.name}` : status
}
</script>
