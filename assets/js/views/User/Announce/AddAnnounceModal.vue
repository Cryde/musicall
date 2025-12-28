<template>
  <Dialog
    v-model:visible="visible"
    modal
    :closable="!userAnnounceStore.isSaving"
    :style="{ width: '95vw', maxWidth: '750px' }"
    :pt="{
      header: { class: 'pb-0 border-0' },
      content: { class: 'pt-4' }
    }"
    @hide="reset"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30">
          <i class="pi pi-megaphone text-primary-600 dark:text-primary-400 text-lg" />
        </div>
        <div>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 m-0">
            Nouvelle annonce
          </h2>
          <p class="text-sm text-surface-500 dark:text-surface-400 m-0">
            Creez une annonce pour trouver des musiciens ou un groupe
          </p>
        </div>
      </div>
    </template>

    <div v-if="isSuccess" class="flex flex-col items-center justify-center py-8">
      <i class="pi pi-check-circle text-6xl text-green-500 mb-4" />
      <p class="text-lg font-medium text-surface-900 dark:text-surface-0">
        Votre annonce a ete creee avec succes !
      </p>
    </div>

    <Stepper v-else v-model:value="currentStep" :linear="!hasInitialValues">
      <StepList>
        <Step value="1">Type</Step>
        <Step value="2">Instrument</Step>
        <Step value="3">Styles</Step>
        <Step value="4">Localisation</Step>
        <Step value="5">Note</Step>
      </StepList>

      <StepPanels>
        <StepPanel v-slot="{ activateCallback }" value="1">
          <div class="flex flex-col gap-4 py-4">
            <p class="text-surface-700 dark:text-surface-300 font-medium">
              Quel type de recherche souhaitez-vous effectuer ?
            </p>
            <div class="flex flex-col gap-3">
              <div
                class="flex items-center gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                :class="selectedType === 'band'
                  ? 'border-primary bg-primary-50 dark:bg-primary-900/20'
                  : 'border-surface-200 dark:border-surface-700 hover:border-primary'"
                @click="selectedType = 'band'"
              >
                <i class="pi pi-users text-2xl" :class="selectedType === 'band' ? 'text-primary' : 'text-surface-500'" />
                <div>
                  <p class="font-semibold text-surface-900 dark:text-surface-0">Je recherche un groupe</p>
                  <p class="text-sm text-surface-500 dark:text-surface-400">Vous etes musicien et cherchez un groupe</p>
                </div>
              </div>
              <div
                class="flex items-center gap-4 p-4 rounded-xl border cursor-pointer transition-all"
                :class="selectedType === 'musician'
                  ? 'border-primary bg-primary-50 dark:bg-primary-900/20'
                  : 'border-surface-200 dark:border-surface-700 hover:border-primary'"
                @click="selectedType = 'musician'"
              >
                <i class="pi pi-user text-2xl" :class="selectedType === 'musician' ? 'text-primary' : 'text-surface-500'" />
                <div>
                  <p class="font-semibold text-surface-900 dark:text-surface-0">Je recherche un musicien</p>
                  <p class="text-sm text-surface-500 dark:text-surface-400">Vous avez un groupe et cherchez un musicien</p>
                </div>
              </div>
            </div>
          </div>
          <div class="flex justify-end pt-4">
            <Button
              label="Suivant"
              icon="pi pi-arrow-right"
              iconPos="right"
              :disabled="!selectedType"
              @click="activateCallback('2')"
            />
          </div>
        </StepPanel>

        <StepPanel v-slot="{ activateCallback }" value="2">
          <div class="flex flex-col gap-4 py-4">
            <p class="text-surface-700 dark:text-surface-300 font-medium">
              {{ selectedType === 'band' ? 'Quel instrument jouez-vous ?' : 'Quel instrument recherchez-vous ?' }}
            </p>
            <Select
              v-model="selectedInstrument"
              :options="instrumentStore.instruments"
              filter
              optionLabel="musician_name"
              placeholder="Selectionnez un instrument"
              class="w-full"
            />
          </div>
          <div class="flex justify-between pt-4">
            <Button
              label="Precedent"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback('1')"
            />
            <Button
              label="Suivant"
              icon="pi pi-arrow-right"
              iconPos="right"
              :disabled="!selectedInstrument"
              @click="activateCallback('3')"
            />
          </div>
        </StepPanel>

        <StepPanel v-slot="{ activateCallback }" value="3">
          <div class="flex flex-col gap-4 py-4">
            <p class="text-surface-700 dark:text-surface-300 font-medium">
              {{ selectedType === 'band' ? 'Quels styles jouez-vous ?' : 'Quels styles recherchez-vous ?' }}
            </p>
            <MultiSelect
              v-model="selectedStyles"
              :options="styleStore.styles"
              optionLabel="name"
              filter
              placeholder="Selectionnez un ou plusieurs styles"
              class="w-full"
              display="chip"
            />
          </div>
          <div class="flex justify-between pt-4">
            <Button
              label="Precedent"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback('2')"
            />
            <Button
              label="Suivant"
              icon="pi pi-arrow-right"
              iconPos="right"
              :disabled="selectedStyles.length === 0"
              @click="activateCallback('4')"
            />
          </div>
        </StepPanel>

        <StepPanel v-slot="{ activateCallback }" value="4">
          <div class="flex flex-col gap-4 py-4">
            <p class="text-surface-700 dark:text-surface-300 font-medium">
              Dans quelle zone geographique ?
            </p>

            <AutoComplete
              v-model="selectedLocation"
              :suggestions="locationSuggestions"
              optionLabel="name"
              placeholder="Rechercher une ville..."
              fluid
              @complete="searchLocation"
            >
              <template #option="{ option }">
                <div class="flex items-center gap-3 py-1">
                  <i class="pi pi-map-marker text-primary" />
                  <div>
                    <div class="font-medium">{{ option.name }}</div>
                    <div v-if="option.context" class="text-sm text-surface-500 dark:text-surface-400">
                      {{ option.context }}
                    </div>
                  </div>
                </div>
              </template>
            </AutoComplete>

            <div v-if="selectedLocation && typeof selectedLocation === 'object'" class="flex items-center gap-2 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
              <i class="pi pi-map-marker text-primary" />
              <span class="text-surface-900 dark:text-surface-0">{{ selectedLocation.fullName }}</span>
            </div>

            <Message severity="info" size="small">
              Indiquez de preference une ville ou commune
            </Message>
          </div>
          <div class="flex justify-between pt-4">
            <Button
              label="Precedent"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback('3')"
            />
            <Button
              label="Suivant"
              icon="pi pi-arrow-right"
              iconPos="right"
              :disabled="!selectedLocation || typeof selectedLocation !== 'object'"
              @click="activateCallback('5')"
            />
          </div>
        </StepPanel>

        <StepPanel v-slot="{ activateCallback }" value="5">
          <div class="flex flex-col gap-4 py-4">
            <p class="text-surface-700 dark:text-surface-300 font-medium">
              Ajoutez une note (optionnel)
            </p>
            <Textarea
              v-model="note"
              rows="4"
              placeholder="Informations complementaires sur votre recherche..."
              class="w-full"
            />

            <div class="bg-surface-100 dark:bg-surface-800 rounded-xl p-4 mt-2">
              <p class="font-semibold text-surface-900 dark:text-surface-0 mb-3">Recapitulatif</p>
              <div class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">Type :</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedType === 'band' ? 'Recherche un groupe' : 'Recherche un musicien' }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">Instrument :</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedInstrument?.musician_name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">Styles :</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedStyles.map(s => s.name).join(', ') }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">Localisation :</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedLocation?.fullName }}</span>
                </div>
              </div>
            </div>
          </div>
          <div class="flex justify-between pt-4">
            <Button
              label="Precedent"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback('4')"
            />
            <Button
              label="Creer l'annonce"
              icon="pi pi-check"
              :loading="userAnnounceStore.isSaving"
              @click="save"
            />
          </div>
        </StepPanel>
      </StepPanels>
    </Stepper>

    <template #footer>
      <div v-if="isSuccess" class="flex justify-end w-full">
        <Button label="Fermer" @click="visible = false" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { useDebounceFn } from '@vueuse/core'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Step from 'primevue/step'
import StepList from 'primevue/steplist'
import StepPanel from 'primevue/steppanel'
import StepPanels from 'primevue/steppanels'
import Stepper from 'primevue/stepper'
import Textarea from 'primevue/textarea'
import { computed, onMounted, ref, watch } from 'vue'
import geocodingApi from '../../../api/geocoding.js'
import { useUserAnnounceStore } from '../../../store/announce/userAnnounce.js'
import { useInstrumentStore } from '../../../store/attribute/instrument.js'
import { useStyleStore } from '../../../store/attribute/style.js'

const props = defineProps({
  initialType: { type: String, default: null },
  initialInstrument: { type: Object, default: null },
  initialStyles: { type: Array, default: () => [] },
  initialLocation: { type: Object, default: null }
})

const emit = defineEmits(['created'])
const visible = defineModel('visible', { type: Boolean, default: false })

const userAnnounceStore = useUserAnnounceStore()
const instrumentStore = useInstrumentStore()
const styleStore = useStyleStore()

const currentStep = ref('1')
const isSuccess = ref(false)

const selectedType = ref(null)
const selectedInstrument = ref(null)
const selectedStyles = ref([])
const selectedLocation = ref(null)
const note = ref('')

// Location search
const locationSuggestions = ref([])

const hasInitialValues = computed(() => {
  return props.initialType || props.initialInstrument || props.initialStyles?.length > 0 || props.initialLocation
})

onMounted(async () => {
  await Promise.all([
    instrumentStore.loadInstruments(),
    styleStore.loadStyles()
  ])
})

// Initialize from props when modal opens
watch(visible, (isVisible) => {
  if (isVisible) {
    initializeFromProps()
  }
})

function initializeFromProps() {
  // Set initial values from props
  if (props.initialType) {
    selectedType.value = props.initialType
  }
  if (props.initialInstrument) {
    selectedInstrument.value = props.initialInstrument
  }
  if (props.initialStyles?.length > 0) {
    selectedStyles.value = props.initialStyles
  }
  if (props.initialLocation) {
    selectedLocation.value = props.initialLocation
  }

  // Find the first step with missing data
  currentStep.value = getFirstMissingStep()
}

function getFirstMissingStep() {
  if (!selectedType.value) return '1'
  if (!selectedInstrument.value) return '2'
  if (selectedStyles.value.length === 0) return '3'
  if (!selectedLocation.value) return '4'
  return '5'
}

const debouncedSearch = useDebounceFn(async (query) => {
  try {
    locationSuggestions.value = await geocodingApi.searchCities(query)
  } catch (error) {
    console.error('Error searching location:', error)
    locationSuggestions.value = []
  }
}, 300)

function searchLocation(event) {
  const query = event.query
  if (query.length >= 2) {
    debouncedSearch(query)
  }
}

async function save() {
  const success = await userAnnounceStore.createAnnounce({
    type: selectedType.value,
    instrument: selectedInstrument.value,
    styles: selectedStyles.value,
    location: {
      name: selectedLocation.value.fullName,
      latitude: selectedLocation.value.latitude,
      longitude: selectedLocation.value.longitude
    },
    note: note.value
  })

  if (success) {
    isSuccess.value = true
    emit('created')
  }
}

function reset() {
  currentStep.value = '1'
  isSuccess.value = false
  selectedType.value = null
  selectedInstrument.value = null
  selectedStyles.value = []
  selectedLocation.value = null
  note.value = ''
  locationSuggestions.value = []
}
</script>
