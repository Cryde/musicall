<template>
  <div class="flex flex-col gap-2" role="group" :aria-labelledby="labelId">
    <div class="flex items-center justify-between">
      <span :id="labelId" class="font-medium text-surface-900 dark:text-surface-0">
        {{ t('profile.musician.edit.bands') }}
      </span>
      <Button
        v-if="bands.length < MAX_BANDS"
        icon="pi pi-plus"
        :label="t('profile.musician.edit.add_band')"
        severity="secondary"
        size="small"
        :disabled="disabled"
        @click="bands.push(emptyBand())"
      />
    </div>
    <p class="text-sm text-surface-500 dark:text-surface-400 m-0">
      {{ t('profile.musician.edit.bands_help', { max: MAX_BANDS }) }}
    </p>

    <div
      v-for="(band, index) in bands"
      :key="index"
      class="flex flex-col gap-2 p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
    >
      <div class="flex items-center gap-2">
        <InputText
          v-model="band.name"
          :aria-label="t('profile.musician.edit.band_name_aria', { number: index + 1 })"
          :placeholder="t('profile.musician.edit.band_name_placeholder')"
          :disabled="disabled"
          maxlength="80"
          class="flex-1 min-w-0"
        />
        <Button
          icon="pi pi-trash"
          severity="danger"
          text
          rounded
          size="small"
          :aria-label="t('profile.musician.edit.remove_band')"
          :disabled="disabled"
          @click="bands.splice(index, 1)"
        />
      </div>
      <div class="grid gap-2 sm:grid-cols-2">
        <Select
          v-model="band.styleId"
          :options="styleStore.styles || []"
          optionLabel="name"
          optionValue="id"
          :aria-label="t('profile.musician.edit.band_style_aria', { number: index + 1 })"
          :placeholder="t('profile.musician.edit.band_style_placeholder')"
          :disabled="disabled"
          filter
          showClear
        />
        <InputText
          v-model="band.role"
          :aria-label="t('profile.musician.edit.band_role_aria', { number: index + 1 })"
          :placeholder="t('profile.musician.edit.band_role_placeholder')"
          :disabled="disabled"
          maxlength="80"
        />
      </div>
      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <SelectButton
          v-model="band.current"
          :options="bandPeriodOptions"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          :aria-label="t('profile.musician.edit.band_period_aria', { number: index + 1 })"
          :disabled="disabled"
        />
        <InputText
          v-model="band.url"
          type="url"
          :aria-label="t('profile.musician.edit.band_url_aria', { number: index + 1 })"
          :placeholder="t('profile.musician.edit.band_url_placeholder')"
          :disabled="disabled"
          maxlength="500"
          class="flex-1 min-w-0"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useStyleStore } from '../../../../store/attribute/style.js'
import { emptyBand, MAX_BANDS } from '../../../../utils/musicianBands.js'

/** The bands a musician plays or played in (#1313), one row each, edited in place. */
defineProps({
  disabled: { type: Boolean, default: false }
})

const bands = defineModel({ type: Array, required: true })
const { t } = useI18n()
const styleStore = useStyleStore()
const labelId = useId()

const bandPeriodOptions = computed(() => [
  { value: true, label: t('profile.musician.edit.band_current') },
  { value: false, label: t('profile.musician.edit.band_past') }
])
</script>
