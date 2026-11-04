<template>
  <Skeleton v-if="isLoading" height="6.5rem" border-radius="1.125rem" />

  <section
    v-else-if="next"
    class="overflow-hidden rounded-[1.125rem] bg-primary text-primary-contrast"
    :aria-label="t('band_space.home.next_date.label')"
  >
    <!-- Named by what it shows, the date included, so a voice command can say what it reads. -->
    <RouterLink :to="agendaItemLink(next, bandSpaceId)" class="flex items-center gap-3.5 p-3.5">
      <span
        class="flex w-14.5 shrink-0 flex-col items-center rounded-[0.8125rem] bg-primary-contrast/15 py-2 leading-tight uppercase"
      >
        <span class="text-[0.6875rem] font-semibold">{{ weekday }}</span>
        <span class="text-2xl font-extrabold">{{ shown.getDate() }}</span>
        <span class="text-[0.6875rem] font-semibold">{{ month }}</span>
      </span>
      <span class="flex min-w-0 flex-1 flex-col gap-0.5">
        <span class="text-xs font-semibold">{{ when }}</span>
        <span class="line-clamp-2 text-[1.0625rem] leading-snug font-semibold">{{ heading }}</span>
        <span v-if="times" class="text-sm opacity-85">{{ times }}</span>
      </span>
      <i class="pi pi-chevron-right text-sm opacity-80" aria-hidden="true" />
    </RouterLink>

    <!-- Under the blue, so the green and red answers keep their contrast. -->
    <div
      v-if="asksAvailability"
      class="flex flex-wrap items-center justify-between gap-2 bg-surface-0 px-3.5 py-2.5 dark:bg-surface-900"
    >
      <span class="text-sm text-surface-600 tabular-nums dark:text-surface-300">{{ summary }}</span>
      <AgendaAvailabilityButtons
        v-if="canAnswerFromAgenda(next)"
        :band-space-id="bandSpaceId"
        :item="next"
        @answered="emit('answered', $event)"
      />
    </div>
  </section>

  <section
    v-else
    class="flex min-h-26 items-center gap-3 rounded-[1.125rem] border-[1.5px] border-dashed border-surface-400 py-3.5 pr-3 pl-4 dark:border-surface-600"
    :aria-label="t('band_space.home.next_date.label')"
  >
    <div class="min-w-0 flex-1">
      <p class="m-0 text-xs font-semibold text-surface-600 dark:text-surface-400">{{ t('band_space.home.next_date.label') }}</p>
      <p class="m-0 text-[1.0625rem] font-semibold text-surface-900 dark:text-surface-0">
        {{ failed ? t('band_space.dashboard.agenda.load_error') : t('band_space.home.nothing_planned') }}
      </p>
      <p v-if="lastPast" class="m-0 mt-0.5 truncate text-sm text-surface-600 dark:text-surface-400">
        {{ t('band_space.home.next_date.last', { title: lastPast.title, date: shortDay(shownDate(lastPast)) }) }}
      </p>
    </div>
    <RouterLink
      :to="{ name: BAND_SPACE_ROUTES.AGENDA, params: { id: bandSpaceId }, query: { new: 1 } }"
      class="flex size-11 shrink-0 items-center justify-center rounded-full border border-surface-400 text-surface-700 transition-colors duration-150 hover:bg-surface-0 dark:border-surface-600 dark:text-surface-200 dark:hover:bg-surface-900"
      :aria-label="t('band_space_agenda.common.new_event')"
    >
      <i class="pi pi-plus" aria-hidden="true" />
    </RouterLink>
  </section>
</template>

<script setup>
import { format } from 'date-fns'
import Skeleton from 'primevue/skeleton'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { BAND_SPACE_ROUTES } from '../../../constants/bandSpace.js'
import { dateFnsLocale, rangeArrow, separator } from '../../../i18n/formatLocale.js'
import { availabilitySummary, canAnswerFromAgenda } from '../../../utils/agendaAvailability.js'
import { toAgendaDate } from '../../../utils/agendaDate.js'
import { agendaItemLink, isAllDayItem } from '../../../utils/agendaItem.js'
import { shortDay, shownDate, whenLabel } from '../../../utils/bandHomeStatus.js'
import AgendaAvailabilityButtons from '../Agenda/AgendaAvailabilityButtons.vue'

/**
 * The band's next date, first on the phone home as in the app's banner: answering it is what members
 * open the Band Space for. With nothing planned, room to add one.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  next: { type: Object, default: null },
  lastPast: { type: Object, default: null },
  isLoading: { type: Boolean, default: false },
  failed: { type: Boolean, default: false },
  // The page's clock, so « Demain » turns into « Aujourd'hui » on a page left open.
  now: { type: Date, required: true }
})

const emit = defineEmits(['answered'])

const { t } = useI18n()

const shown = computed(() => shownDate(props.next))
const weekday = computed(() => format(shown.value, 'EEE', { locale: dateFnsLocale() }))
const month = computed(() => format(shown.value, 'MMM', { locale: dateFnsLocale() }))
const when = computed(() => whenLabel(shown.value, props.now))

const heading = computed(() =>
  [props.next.title, props.next.metadata?.location].filter(Boolean).join(` ${separator()} `)
)

// An all day date has no hours to show.
const times = computed(() => {
  if (isAllDayItem(props.next)) return null
  const start = format(shown.value, 'HH:mm')
  const end = toAgendaDate(props.next.end_datetime, false)
  return end ? `${start} ${rangeArrow()} ${format(end, 'HH:mm')}` : start
})

const asksAvailability = computed(() => props.next?.metadata?.ask_availability === true)
const summary = computed(() => availabilitySummary(props.next?.metadata?.availability))
</script>
