<template>
  <div>
    <Button
      label="Ajouter une section"
      data-rider-add
      icon="pi pi-plus"
      severity="secondary"
      outlined
      class="w-full"
      aria-haspopup="menu"
      :loading="adding"
      @click="(event) => menuRef.toggle(event)"
    />
    <!-- Picked by type, each with what it is for: the type decides the editor, which used to hide
         behind a select under a free title field. -->
    <Menu ref="menuRef" :model="menuItems" :popup="true">
      <template #item="{ item, props: itemProps }">
        <a v-bind="itemProps.action" class="flex items-start gap-3 px-3 py-2">
          <i :class="['pi', item.icon, 'mt-0.5 text-surface-600 dark:text-surface-300']" aria-hidden="true" />
          <span class="flex flex-col">
            <span class="font-medium">{{ item.label }}</span>
            <span class="text-xs text-surface-600 dark:text-surface-300">{{ item.description }}</span>
          </span>
        </a>
      </template>
    </Menu>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref } from 'vue'
import { RIDER_SECTION_TYPES } from '../../../constants/techRider.js'

const props = defineProps({
  /** The types already in the rider, so a second contacts section is flagged as such. */
  presentTypes: { type: Array, default: () => [] },
  adding: { type: Boolean, default: false }
})

const emit = defineEmits(['add'])

const menuRef = ref(null)

const menuItems = computed(() =>
  RIDER_SECTION_TYPES.map((type) => ({
    label: type.label,
    icon: type.icon,
    description:
      type.value === 'contacts' && props.presentTypes.includes('contacts')
        ? 'Déjà présente'
        : type.description,
    command: () => emit('add', type)
  }))
)
</script>
