<template>
  <div class="flex flex-col md:flex-row md:items-start gap-4 py-3">
    <div class="md:w-1/3">
      <div class="text-surface-600 dark:text-surface-400 font-medium">
        {{ label }}
      </div>
      <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
        {{ description }}
      </p>
    </div>
    <div class="md:w-2/3 flex items-center gap-3">
      <ToggleSwitch :modelValue="modelValue" :disabled="disabled" @update:modelValue="$emit('update:modelValue', $event)" />
      <span class="text-surface-700 dark:text-surface-300">
        {{ modelValue ? 'Activé' : 'Désactivé' }}
      </span>
    </div>
  </div>
</template>

<script setup>
import ToggleSwitch from 'primevue/toggleswitch'

defineProps({
  modelValue: {
    type: Boolean,
    default: true
  },
  label: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

defineEmits(['update:modelValue'])
</script>
