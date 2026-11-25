<template>
  <ServiceFormSection :icon="icon" :title="t('profile.service_form.section_service')" class="gap-5">
    <div class="flex flex-col gap-2">
      <label :for="`${idPrefix}-headline`" :class="LABEL_CLASS">{{ t('profile.service_form.headline') }}</label>
      <InputText :id="`${idPrefix}-headline`" v-model="form.headline" :maxlength="150" :placeholder="headlinePlaceholder" :disabled="disabled" />
    </div>
    <fieldset class="flex flex-col gap-2">
      <legend :class="[LABEL_CLASS, 'mb-2']">{{ t('profile.service_form.specialities') }}</legend>
      <div class="flex flex-wrap gap-x-5 gap-y-2">
        <label v-for="speciality in specialities" :key="speciality.value" class="inline-flex items-center gap-2">
          <Checkbox v-model="form.specialities" :value="speciality.value" :disabled="disabled" />
          {{ speciality.label }}
        </label>
      </div>
    </fieldset>
    <div class="flex flex-col gap-2">
      <label :for="`${idPrefix}-description`" :class="LABEL_CLASS">{{ t('profile.service_form.description') }}</label>
      <Textarea :id="`${idPrefix}-description`" v-model="form.description" rows="4" :maxlength="5000" :placeholder="descriptionPlaceholder" :disabled="disabled" />
    </div>
    <div class="grid gap-5 md:grid-cols-2">
      <div class="flex flex-col gap-2">
        <label :for="`${idPrefix}-years`" :class="LABEL_CLASS">{{ yearsLabel }}</label>
        <InputNumber fluid v-model="form.years_of_experience" :input-id="`${idPrefix}-years`" :min="0" :max="70" :disabled="disabled" />
      </div>
      <div class="flex flex-col gap-2">
        <label :for="`${idPrefix}-styles`" :class="LABEL_CLASS">{{ t('profile.service_form.styles') }}</label>
        <MultiSelect
          v-model="form.style_ids"
          :input-id="`${idPrefix}-styles`"
          :options="styleStore.styles || []"
          option-label="name"
          option-value="id"
          :selection-limit="10"
          display="chip"
          filter
          :placeholder="t('profile.teacher.edit.styles_placeholder')"
          :disabled="disabled"
        />
      </div>
    </div>
  </ServiceFormSection>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Textarea from 'primevue/textarea'
import { useI18n } from 'vue-i18n'
import { useStyleStore } from '../../../store/attribute/style.js'
import ServiceFormSection from './ServiceFormSection.vue'

/** « Le service » in a service's form (#1410, #1411): its title, specialities, presentation, experience and styles. */
defineProps({
  /** The kind's icon. */
  icon: { type: String, required: true },
  /** Prefixes the field ids, unique on the page. */
  idPrefix: { type: String, required: true },
  /** `{ value, label }`, in the kind's order. */
  specialities: { type: Array, required: true },
  headlinePlaceholder: { type: String, required: true },
  descriptionPlaceholder: { type: String, required: true },
  /** « Années de studio », « Années d'expérience ». */
  yearsLabel: { type: String, required: true },
  disabled: { type: Boolean, default: false }
})
/** The form as the API gave it: `headline`, `specialities`, `description`, `years_of_experience`, `style_ids`. */
const form = defineModel('form', { type: Object, required: true })

const LABEL_CLASS = 'font-medium text-surface-900 dark:text-surface-0'

const { t } = useI18n()
const styleStore = useStyleStore()
</script>
