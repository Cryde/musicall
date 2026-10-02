<template>
  <div class="flex flex-col gap-2 h-full min-h-0">
    <div class="flex items-center justify-between gap-2 text-sm text-surface-600 dark:text-surface-300">
      <!-- Only the failure is announced: a refresh follows every autosave, and saying so each
           time would talk over the editing. -->
      <span role="status" class="flex items-center gap-2 min-w-0">
        <template v-if="error">
          <i class="pi pi-times text-red-600 dark:text-red-400" aria-hidden="true" />
          <span>Aperçu indisponible</span>
          <Button label="Réessayer" size="small" severity="secondary" text @click="scheduler.refresh()" />
        </template>
      </span>
      <!-- The way in where the browser cannot show a PDF inline, which most phones cannot. -->
      <a
        v-if="pdfUrl"
        :href="pdfUrl"
        target="_blank"
        rel="noopener"
        class="shrink-0 underline hover:no-underline"
      >Ouvrir dans un onglet</a>
    </div>

    <!-- Keyed on its URL: the viewer only reads the destination on load, a new fragment alone
         does not move it. Reloading reads the blob already in memory, it is not a new render, but
         it does put the viewer back on the selected section after every refresh. -->
    <div v-if="pdfUrl" class="relative flex-1 min-h-0 flex">
      <iframe
        :key="viewerUrl"
        :src="viewerUrl"
        title="Aperçu du PDF du tech rider"
        :class="[
          'flex-1 min-h-0 w-full rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-100 dark:bg-surface-800 transition-opacity',
          isOutdated ? 'opacity-50' : ''
        ]"
      />
      <!-- From the edit on, not just during the render: the PDF on screen is already out of date
           while the save and the settling wait run. Not announced, see the status above. -->
      <div
        v-if="isOutdated"
        aria-hidden="true"
        class="pointer-events-none absolute top-14 left-1/2 -translate-x-1/2 flex items-center gap-2 px-3 py-1.5 rounded-full shadow-md text-sm bg-surface-0 text-surface-700 dark:bg-surface-900 dark:text-surface-200 border border-surface-200 dark:border-surface-700"
      >
        <i class="pi pi-spin pi-spinner" />
        <span>Mise à jour de l’aperçu…</span>
      </div>
    </div>
    <div
      v-else
      class="flex-1 min-h-0 flex items-center justify-center rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800"
    >
      <ProgressSpinner v-if="!error" style="width: 2rem; height: 2rem" />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import bandSpaceTechRidersApi from '../../../api/bandSpace/band-space-tech-riders.js'
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'
import { createPdfPreviewScheduler } from '../../../utils/pdfPreviewScheduler.js'

/**
 * The real PDF from the export pipeline, in the browser's own viewer (#1092). Mounted only while the
 * preview is open, which is what keeps a closed preview from costing a render.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  riderId: { type: String, required: true },
  selectedItemId: { type: String, default: null }
})

const techRidersStore = useBandTechRidersStore()

const pdfUrl = ref(null)
const isBusy = ref(false)
const error = ref(false)
let isMounted = true
// A render still out when the preview closes or the rider changes is not worth waiting for.
const abortController = new AbortController()

// The section's named destination, written by the PDF template. A section left out of the PDF,
// or one inside a merged rider (the merge drops destinations), leaves the viewer where it was.
const viewerUrl = computed(() =>
  props.selectedItemId ? `${pdfUrl.value}#nameddest=item-${props.selectedItemId}` : pdfUrl.value
)

// A save still on its way counts too: the change is not in the store yet, so not in the scheduler.
const isOutdated = computed(
  () => !error.value && (isBusy.value || techRidersStore.saveStatus === 'saving')
)

async function render() {
  try {
    const { blob } = await bandSpaceTechRidersApi.downloadPdf(props.bandSpaceId, props.riderId, {
      signal: abortController.signal
    })
    if (!isMounted) return
    const previous = pdfUrl.value
    pdfUrl.value = URL.createObjectURL(blob)
    if (previous) URL.revokeObjectURL(previous)
    error.value = false
  } catch (e) {
    if (isMounted) error.value = true
    throw e
  }
}

const scheduler = createPdfPreviewScheduler({
  render,
  onBusy: (busy) => {
    if (isMounted) isBusy.value = busy
  }
})

// What the PDF prints. The store replaces these once the server has taken a change, and a reorder
// ahead of its answer, which the debounce almost always outlasts; archiving changes neither.
watch(
  () => [techRidersStore.activeTechRider?.items, techRidersStore.activeTechRider?.name],
  () => scheduler.changed()
)

onMounted(() => scheduler.start())

onBeforeUnmount(() => {
  isMounted = false
  scheduler.stop()
  abortController.abort()
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value)
})
</script>
