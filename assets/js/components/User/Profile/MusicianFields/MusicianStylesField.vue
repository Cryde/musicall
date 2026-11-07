<template>
  <div class="flex flex-col gap-2">
    <label :for="fieldId" class="font-medium text-surface-900 dark:text-surface-0">
      {{ t('profile.musician.edit.styles') }}
    </label>
    <MultiSelect
      :inputId="fieldId"
      v-model="styleIds"
      :options="styleStore.styles || []"
      optionLabel="name"
      optionValue="id"
      :placeholder="t('profile.musician.edit.styles_placeholder')"
      :disabled="disabled"
      class="w-full"
      display="chip"
      filter
    />
  </div>
</template>

<script setup>
import MultiSelect from 'primevue/multiselect'
import { useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStyleStore } from '../../../../store/attribute/style.js'

/** The styles a musician plays (#1317). */
defineProps({
  disabled: { type: Boolean, default: false }
})

const styleIds = defineModel({ type: Array, required: true })
const { t } = useI18n()
const styleStore = useStyleStore()
const fieldId = useId()
</script>
