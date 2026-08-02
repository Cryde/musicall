<template>
  <Select
    v-model="model"
    :options="selectOptions"
    option-label="name"
    option-group-label="label"
    option-group-children="items"
    placeholder="Sélectionnez un tech rider"
    class="w-full sm:w-72"
    aria-label="Tech rider affiché"
    @change="handleChange"
  >
    <template #optiongroup="{ option }">
      <span class="text-xs font-semibold uppercase tracking-wide text-surface-500">
        {{ option.label }}
      </span>
    </template>
    <template #option="{ option }">
      <div class="flex items-center gap-2">
        <i v-if="option.isCreateAction" class="pi pi-plus text-sm" aria-hidden="true" />
        <i
          v-else-if="option.archive_datetime"
          class="pi pi-inbox text-sm text-surface-500"
          aria-hidden="true"
        />
        <span :class="{ 'font-semibold': option.isCreateAction }">{{ option.name }}</span>
      </div>
    </template>
  </Select>
</template>

<script setup>
import Select from 'primevue/select'
import { computed, nextTick, ref, watch } from 'vue'
import { CREATE_ACTION_ID } from '../../../constants/bandSpace.js'

const props = defineProps({
  liveRiders: { type: Array, required: true },
  archivedRiders: { type: Array, required: true },
  selectedId: { type: String, default: null }
})

const emit = defineEmits(['select', 'create'])

const selected = computed(
  () =>
    props.liveRiders.find((rider) => rider.id === props.selectedId) ??
    props.archivedRiders.find((rider) => rider.id === props.selectedId) ??
    null
)

/**
 * The Select is bound to a local model rather than straight to `selected`, because "Nouveau
 * tech rider" is an action rather than a value: PrimeVue adopts whatever was picked as its
 * displayed value, so choosing it left the closed Select reading "Nouveau tech rider" while
 * the rider on screen was unchanged. Resyncing from `selected` puts the real answer back.
 */
const model = ref(selected.value)

watch(selected, (rider) => {
  model.value = rider
})

/**
 * Archived riders stay reachable here rather than behind a separate view: a band keeps a
 * couple of riders per year and last year's is the starting point for this year's, so it is
 * a sibling of the live ones, not a deleted thing. The create action sits in the dropdown
 * for the same reason BandSpaceSelector puts it there, it is where you already are when you
 * discover you need a new one.
 */
const selectOptions = computed(() => {
  const groups = []

  if (props.liveRiders.length > 0) {
    groups.push({ label: '', items: props.liveRiders })
  }
  if (props.archivedRiders.length > 0) {
    groups.push({ label: 'Archives', items: props.archivedRiders })
  }
  groups.push({
    label: '',
    items: [{ id: CREATE_ACTION_ID, name: 'Nouveau tech rider', isCreateAction: true }]
  })

  return groups
})

function handleChange(event) {
  const option = event.value
  if (!option) return

  if (option.id === CREATE_ACTION_ID) {
    emit('create')
    // On the next tick, not now: PrimeVue's own v-model write lands after this handler and
    // would overwrite an immediate restore, leaving the closed Select reading "Nouveau tech
    // rider" while the rider on screen is unchanged.
    nextTick(() => {
      model.value = selected.value
    })
    return
  }
  emit('select', option.id)
}
</script>
