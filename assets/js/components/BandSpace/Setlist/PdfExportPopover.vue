<template>
  <Popover ref="popover">
    <div class="flex flex-col gap-3 w-72">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-surface-500 mb-1">
          Mise en page
        </label>
        <SelectButton
          v-model="options.layout"
          :options="layoutOptions"
          option-label="label"
          option-value="value"
        />
      </div>

      <div class="flex flex-col gap-2 pt-1">
        <div class="flex items-center gap-2">
          <Checkbox v-model="options.showTempo" :binary="true" input-id="pdf-tempo" />
          <label for="pdf-tempo" class="text-sm">Afficher le tempo</label>
        </div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="options.showKey" :binary="true" input-id="pdf-key" />
          <label for="pdf-key" class="text-sm">Afficher la tonalité</label>
        </div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="options.showDurations" :binary="true" input-id="pdf-dur" />
          <label for="pdf-dur" class="text-sm">Afficher les durées</label>
        </div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="options.showNotes" :binary="true" input-id="pdf-notes" />
          <label for="pdf-notes" class="text-sm">Afficher les notes</label>
        </div>
        <div class="flex items-center gap-2">
          <Checkbox v-model="options.showTransitions" :binary="true" input-id="pdf-trans" />
          <label for="pdf-trans" class="text-sm">Afficher les transitions</label>
        </div>
      </div>

      <Button label="Télécharger le PDF" icon="pi pi-download" @click="handleExport" />
    </div>
  </Popover>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Popover from 'primevue/popover'
import SelectButton from 'primevue/selectbutton'
import { reactive, ref } from 'vue'
import bandSpaceSetlistsApi from '../../../api/bandSpace/band-space-setlists.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true }
})

const popover = ref(null)

const layoutOptions = [
  { label: 'Large', value: 'large' },
  { label: 'Compact', value: 'compact' }
]

const options = reactive({
  layout: 'large',
  showTempo: true,
  showKey: true,
  showDurations: true,
  showNotes: false,
  showTransitions: false
})

function toggle(event) {
  popover.value?.toggle(event)
}

function handleExport() {
  const url = bandSpaceSetlistsApi.buildPdfUrl(props.bandSpaceId, props.setlistId, { ...options })
  window.open(url, '_blank')
  popover.value?.hide()
}

defineExpose({ toggle })
</script>
