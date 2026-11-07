<template>
  <div class="flex flex-col gap-2">
    <label :for="fieldId" class="font-medium text-surface-900 dark:text-surface-0">
      {{ t('profile.musician.edit.availability') }}
    </label>
    <Select
      :id="fieldId"
      v-model="availabilityStatus"
      :options="availabilityOptions"
      optionLabel="label"
      optionValue="value"
      :placeholder="t('profile.musician.edit.availability_placeholder')"
      :disabled="disabled"
      class="w-full"
      :showClear="clearable"
    />
  </div>
</template>

<script setup>
import Select from 'primevue/select'
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'

/** A musician's availability, in the full musician form and alone from its pencil on the profile (#1317). */
defineProps({
  disabled: { type: Boolean, default: false },
  clearable: { type: Boolean, default: true }
})

const availabilityStatus = defineModel({ type: String, default: null })
const { t } = useI18n()
const fieldId = useId()

const availabilityOptions = computed(() => [
  { value: 'looking_for_band', label: t('profile.musician.availability.looking_for_band') },
  {
    value: 'available_for_sessions',
    label: t('profile.musician.availability.available_for_sessions')
  },
  {
    value: 'open_to_collaborations',
    label: t('profile.musician.availability.open_to_collaborations')
  },
  { value: 'not_available', label: t('profile.musician.availability.not_available') }
])
</script>
