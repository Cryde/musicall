<template>
  <div class="agenda-event-chip text-xs leading-tight overflow-hidden flex flex-col gap-0.5 px-1 py-0.5">
    <div class="flex items-center gap-1 min-w-0">
      <span v-if="timeText" class="font-medium tabular-nums shrink-0">{{ timeText }}</span>
      <span v-if="priorityIcon" :class="['pi shrink-0', priorityIcon.icon, priorityIcon.color]" :title="priorityIcon.label" />
      <span v-if="financeIcon" :class="['pi text-[10px] shrink-0', financeIcon.icon]" :title="financeIcon.label" />
      <span class="truncate font-medium">{{ item.title }}</span>
    </div>

    <div v-if="!isCompact && financeAmount" class="font-semibold tabular-nums">{{ financeAmount }}</div>

    <div v-if="!isCompact && location" class="truncate opacity-90">
      <i class="pi pi-map-marker text-[10px] mr-1" />{{ location }}
    </div>

    <div v-if="!isCompact && assignees.length" class="flex items-center gap-0.5 mt-0.5">
      <Avatar
        v-for="a in visibleAssignees"
        :key="a.id"
        :username="a.username"
        :picture-url="a.profile_picture_url"
        size="sm"
      />
      <span v-if="hiddenAssigneeCount" class="text-[10px] font-medium opacity-90">+{{ hiddenAssigneeCount }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { formatAmount } from '../../../utils/currency.js'
import Avatar from '../../User/Avatar.vue'

const props = defineProps({
  item: { type: Object, required: true },
  timeText: { type: String, default: '' },
  viewType: { type: String, required: true }
})

const isCompact = computed(() => props.viewType === 'dayGridMonth')

const priorityIcon = computed(() => {
  if (props.item.source !== 'task') return null
  const p = props.item.metadata?.priority
  if (p === 'urgent')
    return { icon: 'pi-exclamation-circle', color: 'text-red-200', label: 'Urgent' }
  if (p === 'high') return { icon: 'pi-arrow-up', color: 'text-amber-100', label: 'Priorité haute' }
  return null
})

const financeIcon = computed(() => {
  if (props.item.source !== 'finance') return null
  const t = props.item.metadata?.type
  if (t === 'income') return { icon: 'pi-arrow-up', label: 'Revenu' }
  if (t === 'expense') return { icon: 'pi-arrow-down', label: 'Dépense' }
  return null
})

const financeAmount = computed(() => {
  if (props.item.source !== 'finance') return null
  const m = props.item.metadata ?? {}
  if (m.amount != null) return formatAmount(m.amount)
  if (m.amount_min != null && m.amount_max != null) {
    return `${formatAmount(m.amount_min)} – ${formatAmount(m.amount_max)}`
  }
  return null
})

const location = computed(() =>
  props.item.source === 'manual' ? (props.item.metadata?.location ?? null) : null
)

const assignees = computed(() => props.item.metadata?.assignees ?? [])
const visibleAssignees = computed(() => assignees.value.slice(0, 3))
const hiddenAssigneeCount = computed(() => Math.max(0, assignees.value.length - 3))
</script>
