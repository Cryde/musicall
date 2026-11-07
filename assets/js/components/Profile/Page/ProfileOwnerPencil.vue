<template>
  <button
    type="button"
    class="absolute right-2.5 z-[1] w-8 h-8 rounded-full bg-primary hover:bg-primary-emphasis text-primary-contrast shadow-md flex items-center justify-center transition-colors cursor-pointer"
    :class="top"
    :title="label"
    :aria-label="label"
    @click="emit('edit')"
  >
    <i class="pi pi-pencil text-xs" aria-hidden="true" />
  </button>
</template>

<script setup>
/** The round pencil on a block of the owner's own profile (#1317), opening that block's form. */
defineProps({
  /** Says which block it edits: « Modifier tes instruments ». */
  label: { type: String, required: true },
  /** Lower on a card that starts with a picture. */
  top: { type: String, default: 'top-2.5' }
})

const emit = defineEmits(['edit'])
</script>
