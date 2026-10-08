<template>
  <div class="flex flex-col md:flex-row md:items-center gap-3 p-4 rounded-lg bg-surface-50 dark:bg-surface-800">
    <div class="flex items-center gap-3 md:w-56 shrink-0">
      <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30">
        <i :class="['pi text-lg text-primary-600 dark:text-primary-400', announce.type === TYPES_ANNOUNCE_BAND ? 'pi-users' : 'pi-user']" />
      </div>
      <div>
        <Tag :value="typeName" :severity="typeSeverity" size="small" />
        <p class="text-sm font-medium text-surface-900 dark:text-surface-0 mt-1">
          {{ announce.instrument_name }}
        </p>
      </div>
    </div>

    <div class="flex-1">
      <div class="flex flex-wrap gap-1">
        <Tag
          v-for="style in announce.styles"
          :key="style"
          :value="style"
          severity="info"
          size="small"
        />
      </div>
    </div>

    <div class="flex items-center gap-1 text-sm text-surface-500 dark:text-surface-400">
      <i class="pi pi-map-marker text-xs" />
      {{ announce.location_name }}
    </div>

    <!-- The message then carries this announce, so the conversation still says why it exists (#998). -->
    <Button
      v-if="contactable"
      label="Contacter"
      aria-label="Contacter à propos de cette annonce"
      title="Contacter à propos de cette annonce"
      icon="pi pi-envelope"
      size="small"
      outlined
      class="self-start md:self-center shrink-0"
      @click="emit('contact', announce)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { computed } from 'vue'
import { TYPES_ANNOUNCE_BAND } from '../../../constants/types.js'

const props = defineProps({
  announce: {
    type: Object,
    required: true
  },
  // Someone else's profile: offer to write about this announce.
  contactable: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['contact'])

const typeName = computed(() => {
  return props.announce.type === TYPES_ANNOUNCE_BAND ? 'Cherche un groupe' : 'Cherche un musicien'
})

const typeSeverity = computed(() => {
  return props.announce.type === TYPES_ANNOUNCE_BAND ? 'success' : 'info'
})
</script>
