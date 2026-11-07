<template>
  <section
    v-if="content"
    class="relative bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5"
    :class="{ [OWNER_CARD_CLASS]: editable }"
    aria-labelledby="profile-instruments-title"
  >
    <ProfileOwnerPencil v-if="editable" :label="t('profile.owner.edit.instruments')" @edit="emit('edit')" />
    <h2 id="profile-instruments-title" class="m-0 mb-1 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.musician_page.instruments') }}
    </h2>
    <ul v-if="content === 'items'" class="list-none m-0 p-0 divide-y divide-surface-200 dark:divide-surface-700">
      <li v-for="instrument in instruments" :key="instrument.instrument_id" class="flex items-center gap-3 py-2.5">
        <span class="flex-1 min-w-0 text-sm text-surface-900 dark:text-surface-0 truncate">{{ instrument.instrument_name }}</span>
        <span class="flex gap-0.5" aria-hidden="true">
          <i
            v-for="step in SKILL_STEP_COUNT"
            :key="step"
            class="block w-4 h-1.5 rounded-sm"
            :class="step <= skillSteps(instrument.skill_level) ? 'bg-primary' : 'bg-surface-200 dark:bg-surface-700'"
          />
        </span>
        <span class="w-24 text-right text-xs text-surface-600 dark:text-surface-300">{{ instrument.skill_level_label }}</span>
      </li>
    </ul>
    <p v-else class="m-0 mt-2 text-sm text-surface-500 dark:text-surface-400">{{ t('profile.owner.hint.instruments') }}</p>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { OWNER_CARD_CLASS, ownerCardContent } from '../../../utils/profileOwner.js'
import { SKILL_STEP_COUNT, skillSteps } from '../../../utils/skillLevel.js'
import ProfileOwnerPencil from './ProfileOwnerPencil.vue'

/** The instruments a musician plays, each with their level in four bars (#1317). */
const props = defineProps({
  instruments: { type: Array, default: () => [] },
  /** The owner's own musician profile: a pencil, and a hint when there is nothing yet. */
  editable: { type: Boolean, default: false }
})

const emit = defineEmits(['edit'])
const { t } = useI18n()
const content = computed(() => ownerCardContent(props.instruments, props.editable))
</script>
