<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    header="Modifier le profil musicien"
    :style="{ width: '40rem' }"
    :closable="!isSaving"
    :closeOnEscape="!isSaving"
  >
    <Message v-if="error" severity="error" :closable="false" class="mb-4">
      {{ error }}
    </Message>

    <div class="flex flex-col gap-5">
      <!-- Availability status -->
      <div class="flex flex-col gap-2">
        <label for="availabilityStatus" class="font-medium text-surface-900 dark:text-surface-0">
          Disponibilité
        </label>
        <Select
          id="availabilityStatus"
          v-model="availabilityStatus"
          :options="availabilityOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionnez votre disponibilité"
          :disabled="isSaving"
          class="w-full"
          showClear
        />
      </div>

      <!-- Instruments -->
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <label class="font-medium text-surface-900 dark:text-surface-0">
            Instruments
          </label>
          <Button
            icon="pi pi-plus"
            label="Ajouter"
            severity="secondary"
            size="small"
            :disabled="isSaving"
            @click="addInstrument"
          />
        </div>

        <div v-if="instruments.length === 0" class="text-surface-500 dark:text-surface-400 text-sm py-2">
          Aucun instrument ajouté
        </div>

        <div v-else class="flex flex-col gap-3">
          <div
            v-for="(instrument, index) in instruments"
            :key="index"
            class="flex items-center gap-3 p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
          >
            <Select
              v-model="instrument.instrumentId"
              :options="instrumentOptions"
              optionLabel="musician_name"
              optionValue="id"
              placeholder="Instrument"
              :disabled="isSaving"
              class="flex-1"
            />
            <Select
              v-model="instrument.skillLevel"
              :options="skillLevelOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Niveau"
              :disabled="isSaving"
              class="w-40"
            />
            <Button
              icon="pi pi-trash"
              severity="danger"
              text
              rounded
              size="small"
              :disabled="isSaving"
              @click="removeInstrument(index)"
            />
          </div>
        </div>
      </div>

      <!-- Styles -->
      <div class="flex flex-col gap-2">
        <label for="styles" class="font-medium text-surface-900 dark:text-surface-0">
          Styles musicaux
        </label>
        <MultiSelect
          id="styles"
          v-model="selectedStyleIds"
          :options="styleOptions"
          optionLabel="name"
          optionValue="id"
          placeholder="Sélectionnez vos styles"
          :disabled="isSaving"
          class="w-full"
          display="chip"
          filter
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Annuler"
          severity="secondary"
          :disabled="isSaving"
          @click="handleClose"
        />
        <Button
          label="Enregistrer"
          icon="pi pi-check"
          :loading="isSaving"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { computed, onMounted, ref, watch } from 'vue'
import { useInstrumentStore } from '../../../store/attribute/instrument.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  musicianProfile: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:visible', 'saved'])

const musicianProfileStore = useMusicianProfileStore()
const instrumentStore = useInstrumentStore()
const styleStore = useStyleStore()

const availabilityStatus = ref(null)
const instruments = ref([])
const selectedStyleIds = ref([])
const error = ref('')
const isSaving = ref(false)

const instrumentOptions = computed(() => instrumentStore.instruments || [])
const styleOptions = computed(() => styleStore.styles || [])

const availabilityOptions = [
  { value: 'looking_for_band', label: 'Cherche un groupe' },
  { value: 'available_for_sessions', label: 'Disponible pour sessions/concerts' },
  { value: 'open_to_collaborations', label: 'Ouvert aux collaborations' },
  { value: 'not_available', label: 'Non disponible' }
]

const skillLevelOptions = [
  { value: 'beginner', label: 'Débutant' },
  { value: 'intermediate', label: 'Intermédiaire' },
  { value: 'advanced', label: 'Avancé' },
  { value: 'professional', label: 'Professionnel' }
]

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

watch(() => props.visible, (visible) => {
  if (visible) {
    initForm()
    loadAttributesIfNeeded()
  }
})

function initForm() {
  error.value = ''

  if (props.musicianProfile) {
    availabilityStatus.value = props.musicianProfile.availability_status || null

    instruments.value = (props.musicianProfile.instruments || []).map((i) => ({
      instrumentId: i.instrument_id,
      skillLevel: i.skill_level
    }))

    selectedStyleIds.value = (props.musicianProfile.styles || []).map((s) => s.id)
  } else {
    availabilityStatus.value = null
    instruments.value = []
    selectedStyleIds.value = []
  }
}

async function loadAttributesIfNeeded() {
  if (!instrumentStore.instruments?.length) {
    await instrumentStore.loadInstruments()
  }
  if (!styleStore.styles?.length) {
    await styleStore.loadStyles()
  }
}

function addInstrument() {
  instruments.value.push({
    instrumentId: null,
    skillLevel: null
  })
}

function removeInstrument(index) {
  instruments.value.splice(index, 1)
}

function handleClose() {
  error.value = ''
  emit('update:visible', false)
}

async function handleSave() {
  error.value = ''
  isSaving.value = true

  // Filter out incomplete instruments and remove duplicates (keep last occurrence)
  const seenInstruments = new Set()
  const validInstruments = instruments.value
    .filter((i) => i.instrumentId && i.skillLevel)
    .reverse()
    .filter((i) => {
      if (seenInstruments.has(i.instrumentId)) {
        return false
      }
      seenInstruments.add(i.instrumentId)
      return true
    })
    .reverse()

  const data = {
    availability_status: availabilityStatus.value || null,
    instruments: validInstruments.map((i) => ({
      instrument_id: i.instrumentId,
      skill_level: i.skillLevel
    })),
    style_ids: selectedStyleIds.value
  }

  try {
    if (props.musicianProfile) {
      await musicianProfileStore.updateProfile(data)
    } else {
      await musicianProfileStore.createProfile(data)
    }
    emit('update:visible', false)
    emit('saved')
  } catch (e) {
    if (e.violations?.length) {
      error.value = e.violations.map((v) => v.message).join('. ')
    } else {
      error.value = e.message || 'Une erreur est survenue'
    }
  } finally {
    isSaving.value = false
  }
}

onMounted(() => {
  loadAttributesIfNeeded()
})
</script>
