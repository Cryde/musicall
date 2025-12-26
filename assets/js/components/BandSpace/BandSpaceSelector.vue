<template>
  <Select
    :modelValue="currentSpace"
    :options="selectOptions"
    optionLabel="name"
    optionGroupLabel="label"
    optionGroupChildren="items"
    placeholder="Selectionnez votre band space"
    class="w-full md:w-56"
    :disabled="bandSpaceStore.isCreating"
    @change="handleSpaceChange"
  >
    <template #optiongroup="slotProps">
      <div class="flex items-center" v-if="slotProps.option.label">
        <div>{{ slotProps.option.label }}</div>
      </div>
    </template>
    <template #option="slotProps">
      <div class="flex items-center gap-2">
        <i v-if="slotProps.option.isCreateAction" class="pi pi-plus" />
        <span :class="{ 'font-semibold': slotProps.option.isCreateAction }">
          {{ slotProps.option.name }}
        </span>
      </div>
    </template>
  </Select>
</template>

<script setup>
import Select from 'primevue/select'
import { computed } from 'vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { BAND_SPACE_ROUTES, CREATE_ACTION_ID } from '../../constants/bandSpace.js'
import { useBandSpaceStore } from '../../store/bandSpace/bandSpace.js'

const bandSpaceStore = useBandSpaceStore()
const { currentSpace, navigateToSpace } = useBandSpaceNavigation()

const selectOptions = computed(() => {
  const options = []

  if (bandSpaceStore.hasSpaces) {
    options.push({
      label: '',
      items: bandSpaceStore.spaces
    })
  }

  options.push({
    label: '',
    items: [{ id: CREATE_ACTION_ID, name: 'Créer un Band Space', isCreateAction: true }]
  })

  return options
})

function handleSpaceChange(event) {
  const selected = event.value
  if (!selected) return

  if (selected.id === CREATE_ACTION_ID) {
    bandSpaceStore.openCreateModal()
    return
  }

  navigateToSpace(selected.id, BAND_SPACE_ROUTES.DASHBOARD)
}
</script>
