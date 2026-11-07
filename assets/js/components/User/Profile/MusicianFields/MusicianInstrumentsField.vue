<template>
  <div class="flex flex-col gap-2">
    <div class="flex items-center justify-between">
      <span class="font-medium text-surface-900 dark:text-surface-0">
        {{ t('profile.musician.edit.instruments') }}
      </span>
      <Button
        icon="pi pi-plus"
        :label="t('profile.musician.edit.add')"
        severity="secondary"
        size="small"
        :disabled="disabled"
        @click="rows.push({ instrumentId: null, skillLevel: null })"
      />
    </div>

    <div v-if="rows.length === 0" class="text-surface-500 dark:text-surface-400 text-sm py-2">
      {{ t('profile.musician.edit.no_instrument') }}
    </div>

    <div v-else class="flex flex-col gap-3">
      <div
        v-for="(row, index) in rows"
        :key="index"
        class="flex items-center gap-3 p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
      >
        <Select
          v-model="row.instrumentId"
          :options="instrumentStore.instruments || []"
          optionLabel="musician_name"
          optionValue="id"
          :placeholder="t('profile.musician.edit.instrument_placeholder')"
          :aria-label="t('profile.musician.edit.instrument_aria', { number: index + 1 })"
          :disabled="disabled"
          class="flex-1 min-w-0"
        />
        <Select
          v-model="row.skillLevel"
          :options="skillLevelOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('profile.musician.edit.level_placeholder')"
          :aria-label="t('profile.musician.edit.level_aria', { number: index + 1 })"
          :disabled="disabled"
          class="w-40"
        />
        <Button
          icon="pi pi-trash"
          severity="danger"
          text
          rounded
          size="small"
          :aria-label="t('profile.musician.edit.remove_instrument', { number: index + 1 })"
          :disabled="disabled"
          @click="rows.splice(index, 1)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Select from 'primevue/select'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useInstrumentStore } from '../../../../store/attribute/instrument.js'

/** A musician's instruments with their level, one row each (#1317). The rows are edited in place. */
defineProps({
  disabled: { type: Boolean, default: false }
})

const rows = defineModel({ type: Array, required: true })
const { t } = useI18n()
const instrumentStore = useInstrumentStore()

const skillLevelOptions = computed(() => [
  { value: 'beginner', label: t('profile.musician.skill_level.beginner') },
  { value: 'intermediate', label: t('profile.musician.skill_level.intermediate') },
  { value: 'advanced', label: t('profile.musician.skill_level.advanced') },
  { value: 'professional', label: t('profile.musician.skill_level.professional') }
])
</script>
