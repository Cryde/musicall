<template>
  <!-- The source is the coloured rule on the left, the chips above being its legend, so the title has
       the whole width and wraps rather than being cut. -->
  <div
    class="flex items-start gap-2 border-l-4 border-b border-surface-200 py-3 pr-2 pl-3 last:border-b-0 dark:border-surface-700"
    :class="agendaSourceFor(item.source).borderClass"
    @click="emit('open', item)"
  >
    <div class="min-w-0 flex-1">
      <p class="m-0 flex items-center gap-1.5 text-xs text-surface-500 tabular-nums dark:text-surface-400">
        <span v-if="timeLabel">{{ timeLabel }}</span>
        <span v-else class="italic">{{ t('band_space_agenda.common.all_day') }}</span>
        <i
          v-if="item.metadata?.is_recurring_occurrence"
          class="pi pi-replay text-[0.65rem]"
          :aria-label="t('band_space_agenda.chip.recurring')"
          role="img"
        />
      </p>
      <!-- The title is the row's button, so a keyboard and a screen reader reach it. -->
      <button
        type="button"
        class="block w-full text-left font-semibold text-surface-900 [overflow-wrap:anywhere] dark:text-surface-0"
        @click.stop="emit('open', item)"
      >
        {{ item.title }}
        <span v-if="leavesTheAgenda" class="sr-only">
          {{ t('band_space_agenda.phone.opens_in', { module: agendaSourceFor(item.source).chipLabel }) }}
        </span>
      </button>

      <div v-if="metaLine || assignees.length" class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-surface-500 dark:text-surface-400">
        <span v-if="metaLine">{{ metaLine }}</span>
        <span v-if="assignees.length" class="flex items-center gap-0.5">
          <Avatar
            v-for="assignee in assignees.slice(0, 3)"
            :key="assignee.id"
            :username="assignee.username"
            :display-name="assignee.display_name"
            :picture-url="assignee.profile_picture_url"
            size="sm"
          />
          <span v-if="assignees.length > 3" class="font-medium">+{{ assignees.length - 3 }}</span>
        </span>
      </div>

      <template v-if="item.source === 'manual' && item.metadata?.ask_availability">
        <p
          v-if="availabilitySummary(item.metadata.availability)"
          class="m-0 mt-1 flex items-center gap-1 text-xs text-surface-600 dark:text-surface-300"
        >
          <i class="pi pi-users text-[0.7rem]" aria-hidden="true" />
          {{ availabilitySummary(item.metadata.availability) }}
        </p>
        <AgendaAvailabilityButtons
          v-if="canAnswerFromAgenda(item)"
          class="mt-2"
          :bandSpaceId="bandSpaceId"
          :item="item"
          roomy
          @answered="(answer) => emit('answered', answer)"
        />
      </template>
    </div>

    <!-- Where the row goes: a chevron opens it here, an arrow leaves for its own module. -->
    <i
      class="pi mt-5 shrink-0 text-xs text-surface-400"
      :class="leavesTheAgenda ? 'pi-arrow-up-right' : 'pi-chevron-right'"
      aria-hidden="true"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { agendaSourceFor } from '../../../constants/agendaSources.js'
import { availabilitySummary, canAnswerFromAgenda } from '../../../utils/agendaAvailability.js'
import { agendaItemMetaLine, agendaItemTimeLabel } from '../../../utils/agendaItem.js'
import Avatar from '../../User/Avatar.vue'
import AgendaAvailabilityButtons from './AgendaAvailabilityButtons.vue'

/** One agenda item on a phone: the hours, the title at full width, one line of detail, the answers. */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  item: { type: Object, required: true }
})

const emit = defineEmits(['open', 'answered'])
const { t } = useI18n()

const timeLabel = computed(() => agendaItemTimeLabel(props.item))
const metaLine = computed(() => agendaItemMetaLine(props.item))
const assignees = computed(() =>
  props.item.source === 'task' ? (props.item.metadata?.assignees ?? []) : []
)
const leavesTheAgenda = computed(
  () => props.item.source === 'task' || props.item.source === 'finance'
)
</script>
