<template>
  <Popover ref="popover">
    <div class="flex flex-col gap-3 w-80">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-surface-500 mb-1">
          Police
        </label>
        <Select
          v-model="options.font"
          :options="fontOptions"
          option-label="label"
          option-value="value"
          class="w-full"
        />
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-surface-500 mb-1">
          Mise en page
        </label>
        <SelectButton
          v-model="options.layout"
          :options="layoutOptions"
          option-label="label"
          option-value="value"
          :allow-empty="false"
        />
      </div>

      <div
        class="flex flex-col gap-2 pt-1"
        v-tooltip.top="isCompact ? 'Ne s’applique qu’en mode Large' : ''"
      >
        <div class="flex items-center gap-2" :class="isCompact && 'opacity-50'">
          <Checkbox
            v-model="options.showTempo"
            :binary="true"
            input-id="pdf-tempo"
            :disabled="isCompact"
          />
          <label for="pdf-tempo" class="text-sm">Afficher le tempo</label>
        </div>
        <div class="flex items-center gap-2" :class="isCompact && 'opacity-50'">
          <Checkbox
            v-model="options.showKey"
            :binary="true"
            input-id="pdf-key"
            :disabled="isCompact"
          />
          <label for="pdf-key" class="text-sm">Afficher la tonalité</label>
        </div>
        <div class="flex items-center gap-2" :class="isCompact && 'opacity-50'">
          <Checkbox
            v-model="options.showDurations"
            :binary="true"
            input-id="pdf-dur"
            :disabled="isCompact"
          />
          <label for="pdf-dur" class="text-sm">Afficher les durées</label>
        </div>
        <div class="flex items-center gap-2" :class="isCompact && 'opacity-50'">
          <Checkbox
            v-model="options.showNotes"
            :binary="true"
            input-id="pdf-notes"
            :disabled="isCompact"
          />
          <label for="pdf-notes" class="text-sm">Afficher les notes</label>
        </div>
        <div class="flex items-center gap-2" :class="isCompact && 'opacity-50'">
          <Checkbox
            v-model="options.showTransitions"
            :binary="true"
            input-id="pdf-trans"
            :disabled="isCompact"
          />
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
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, reactive, ref } from 'vue'
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

const fontOptions = [
  { label: 'Inter (sans-serif)', value: 'inter' },
  { label: 'Atkinson Hyperlegible (lisibilité scène)', value: 'atkinson_hyperlegible' },
  { label: 'Source Serif (serif)', value: 'source_serif' }
]

const options = reactive({
  layout: 'large',
  showTempo: true,
  showKey: true,
  showDurations: true,
  showNotes: false,
  showTransitions: false,
  font: 'inter'
})

const isCompact = computed(() => options.layout === 'compact')

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
