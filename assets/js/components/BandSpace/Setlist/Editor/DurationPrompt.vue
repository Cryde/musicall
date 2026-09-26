<template>
  <Popover ref="popover" @hide="error = null">
    <form class="flex flex-col gap-2 w-60" @submit.prevent="submit">
      <label :for="inputId" class="text-sm font-medium">{{ label }}</label>
      <div class="flex items-center gap-2">
        <InputText
          :id="inputId"
          ref="field"
          v-model="value"
          placeholder="3:47"
          class="flex-1 tabular-nums"
          :invalid="!!error"
          :aria-describedby="error ? `${inputId}-error` : undefined"
        />
        <Button type="submit" label="OK" size="small" :loading="saving" />
      </div>
      <small v-if="error" :id="`${inputId}-error`" class="text-red-600 dark:text-red-400">{{ error }}</small>
      <small v-else class="text-surface-600 dark:text-surface-300">Minutes et secondes, par exemple 3:47.</small>
      <Button
        v-if="clearable && hadValue"
        type="button"
        label="Retirer"
        severity="secondary"
        text
        size="small"
        class="self-start"
        @click="emit('submit', null)"
      />
    </form>
  </Popover>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Popover from 'primevue/popover'
import { nextTick, ref, useId } from 'vue'
import { formatDuration, parseDurationInput } from '../../../../utils/setlistDuration.js'

/**
 * A duration typed as mm:ss, in a popover next to what opened it: « + durée » on a row, the set's
 * target. The caller saves and then calls hide().
 */
const props = defineProps({
  label: { type: String, required: true },
  /** Offers « Retirer », which submits null. */
  clearable: { type: Boolean, default: false },
  /** A floor above the format's own, checked here rather than after a round trip: the target is 60. */
  minSeconds: { type: Number, default: null },
  saving: { type: Boolean, default: false }
})

const emit = defineEmits(['submit'])

const popover = ref(null)
const field = ref(null)
const value = ref('')
const error = ref(null)
const hadValue = ref(false)
const inputId = useId()

async function open(event, seconds = null) {
  value.value = seconds ? formatDuration(seconds) : ''
  hadValue.value = Boolean(seconds)
  error.value = null
  popover.value?.show(event)
  await nextTick()
  field.value?.$el?.focus()
}

function submit() {
  const parsed = parseDurationInput(value.value)
  if (parsed.error || parsed.seconds === null) {
    error.value = parsed.error ?? 'Indiquez une durée.'
    return
  }
  if (props.minSeconds !== null && parsed.seconds < props.minSeconds) {
    error.value = `Au moins ${formatDuration(props.minSeconds)}.`
    return
  }
  emit('submit', parsed.seconds)
}

function hide() {
  popover.value?.hide()
}

defineExpose({ open, hide })
</script>
