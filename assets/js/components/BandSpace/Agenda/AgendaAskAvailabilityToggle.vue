<template>
  <!--
    On a saved entry the switch acts at once, right above the block it shows: the block reads the
    stored answers, which the API only serves once the entry asks.
  -->
  <div class="flex flex-col gap-1">
    <div class="flex items-center gap-2">
      <Checkbox
        :modelValue="modelValue"
        inputId="agenda-ask-availability-saved"
        binary
        :disabled="saving"
        @update:modelValue="(value) => emit('toggle', value)"
      />
      <label for="agenda-ask-availability-saved" class="text-sm font-medium select-none">
        {{ t('band_space_agenda.entry.ask_availability') }}
      </label>
      <i v-if="saving" class="pi pi-spin pi-spinner text-xs text-surface-500" aria-hidden="true" />
    </div>
    <small v-if="error" class="text-red-600 dark:text-red-400">{{ error }}</small>
    <small v-else-if="!modelValue" class="text-surface-600 dark:text-surface-300">
      {{ t('band_space_agenda.entry.ask_availability_kept') }}
    </small>
    <small v-if="isSeries" class="text-surface-600 dark:text-surface-300">
      {{ t('band_space_agenda.entry.ask_availability_series') }}
    </small>
  </div>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import { useI18n } from 'vue-i18n'

/** A saved entry's « Demander les disponibilités », saved as soon as it moves: at the top on a computer, near the end of the form on a phone. */
defineProps({
  modelValue: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  error: { type: String, default: null },
  isSeries: { type: Boolean, default: false }
})

const emit = defineEmits(['toggle'])
const { t } = useI18n()
</script>
