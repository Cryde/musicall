<template>
  <div class="flex flex-col gap-2">
    <label class="text-sm font-medium">{{ t('band_space_finance.entry_drawer.status') }}</label>
    <div v-if="isEditMode" class="flex items-center gap-2 flex-wrap">
      <span v-if="!isBelowMd" class="text-xs font-semibold px-2.5 py-1 rounded-full" :class="badgeClass">
        {{ statusLabel }}
      </span>
      <template v-if="canEdit">
        <!-- On a phone, buttons a thumb finds: « Payé » filled, going back outlined. -->
        <Button
          v-for="transition in transitions"
          :key="transition.value"
          :label="transition.label"
          :severity="transition.severity"
          :icon="isBelowMd && transition.value === 'paid' ? 'pi pi-check' : undefined"
          size="small"
          :text="!isBelowMd"
          :outlined="isBelowMd && transition.value !== 'paid'"
          class="max-md:flex-auto"
          @click="emit('transition', transition.value)"
        />
      </template>
    </div>
    <Select
      v-else
      id="finance-status"
      v-model="status"
      :aria-label="t('band_space_finance.entry_drawer.status')"
      :options="options"
      optionLabel="label"
      optionValue="value"
      :placeholder="t('band_space_finance.entry_drawer.select_status')"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Select from 'primevue/select'
import { useI18n } from 'vue-i18n'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'

/**
 * An entry's status in its drawer: picked on creation, moved on by its transitions once saved. Its own
 * component because a phone shows it right under the label, where the drawer has to render it
 * elsewhere in the form, so the reading order and the tab order stay the same.
 */
defineProps({
  isEditMode: { type: Boolean, required: true },
  canEdit: { type: Boolean, default: true },
  // [{ value, label, severity }]
  transitions: { type: Array, default: () => [] },
  // [{ value, label }], for the creation select
  options: { type: Array, default: () => [] },
  statusLabel: { type: String, default: '' },
  badgeClass: { type: String, default: '' }
})

const emit = defineEmits(['transition'])
const status = defineModel('status', { type: String, default: 'planned' })
const { t } = useI18n()
const isBelowMd = useIsBelowMd()
</script>
