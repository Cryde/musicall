<template>
  <BottomSheet v-model:visible="visible" :title="t('band_space.period_sheet.title')">
    <div class="flex flex-col gap-4 px-5 pb-4">
      <div class="flex flex-wrap gap-2">
        <button
          v-for="preset in presets"
          :key="preset.key"
          type="button"
          :class="[PHONE_CHIP, isPresetActive(preset) ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
          :aria-pressed="isPresetActive(preset)"
          @click="applyPreset(preset)"
        >
          {{ preset.label }}
        </button>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex min-w-0 flex-col gap-1">
          <label :for="`${uid}-from`" class="text-sm font-medium">{{ t('band_space.period_sheet.from') }}</label>
          <DatePicker
            v-model="draftFrom"
            :input-id="`${uid}-from`"
            date-format="d M yy"
            :manual-input="false"
            show-icon
            fluid
          />
        </div>
        <div class="flex min-w-0 flex-col gap-1">
          <label :for="`${uid}-to`" class="text-sm font-medium">{{ t('band_space.period_sheet.to') }}</label>
          <DatePicker
            v-model="draftTo"
            :input-id="`${uid}-to`"
            date-format="d M yy"
            :manual-input="false"
            :min-date="draftFrom ?? undefined"
            show-icon
            fluid
          />
        </div>
      </div>

      <Button :label="t('band_space.period_sheet.apply')" :disabled="!canApply" @click="applyDraft" />
    </div>
  </BottomSheet>
</template>

<script setup>
import { isSameDay, startOfDay } from 'date-fns'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import { computed, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { PHONE_CHIP, PHONE_CHIP_OFF, PHONE_CHIP_ON } from '../../constants/phoneControls.js'
import BottomSheet from '../Global/BottomSheet.vue'

/**
 * A module's period on a phone (agenda, finances): the presets one tap away, or two days and
 * « Appliquer ». The same `apply` as the desktop's range picker, so the page has one way in.
 */
const props = defineProps({
  from: { type: Date, required: true },
  to: { type: Date, required: true },
  // [{ key, label, from: () => Date, to: () => Date }]
  presets: { type: Array, default: () => [] }
})

const emit = defineEmits(['apply'])
const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()
const uid = useId()

const draftFrom = ref(props.from)
const draftTo = ref(props.to)

watch(visible, (isOpen) => {
  if (!isOpen) return
  draftFrom.value = props.from
  draftTo.value = props.to
})

const canApply = computed(
  () => draftFrom.value !== null && draftTo.value !== null && draftTo.value >= draftFrom.value
)

function isPresetActive(preset) {
  return isSameDay(preset.from(), props.from) && isSameDay(preset.to(), props.to)
}

function applyPreset(preset) {
  emit('apply', { from: preset.from(), to: preset.to() })
  visible.value = false
}

function applyDraft() {
  if (!canApply.value) return
  emit('apply', { from: startOfDay(draftFrom.value), to: startOfDay(draftTo.value) })
  visible.value = false
}
</script>
