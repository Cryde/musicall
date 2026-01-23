<template>
  <div class="flex flex-col gap-4 py-4">
    <!-- Loading state -->
    <div v-if="musicianProfileStore.isLoading" class="flex items-center justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl text-primary" />
    </div>

    <!-- Has profile - Summary view -->
    <template v-else-if="musicianProfileStore.profile && !isEditing">
      <div class="text-center mb-2">
        <p class="text-surface-700 dark:text-surface-300 font-medium">
          {{ messages.title }}
        </p>
        <p class="text-sm text-surface-500 dark:text-surface-400">
          {{ messages.cta }}
        </p>
      </div>

      <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4">
        <div class="flex items-center gap-2 mb-3">
          <i class="pi pi-check-circle text-green-500" />
          <span class="font-medium text-surface-900 dark:text-surface-0">Votre profil musicien</span>
        </div>

        <div v-if="musicianProfileStore.profile.availability_status" class="mb-2">
          <Tag :value="getAvailabilityLabel(musicianProfileStore.profile.availability_status)" />
        </div>

        <div v-if="musicianProfileStore.profile.instruments?.length" class="mb-2 text-sm">
          <span class="text-surface-500 dark:text-surface-400">Instruments : </span>
          <span class="text-surface-900 dark:text-surface-0">
            {{ musicianProfileStore.profile.instruments.map(i => i.instrument_name).join(', ') }}
          </span>
        </div>

        <div v-if="musicianProfileStore.profile.styles?.length" class="text-sm">
          <span class="text-surface-500 dark:text-surface-400">Styles : </span>
          <span class="text-surface-900 dark:text-surface-0">
            {{ musicianProfileStore.profile.styles.map(s => s.name).join(', ') }}
          </span>
        </div>
      </div>

      <div class="flex justify-between pt-4">
        <Button
          label="Modifier"
          severity="secondary"
          icon="pi pi-pencil"
          @click="isEditing = true"
        />
        <Button
          label="Continuer"
          icon="pi pi-arrow-right"
          iconPos="right"
          @click="handleContinue"
        />
      </div>
    </template>

    <!-- Simplified flow: musician searching for band - only ask for skill level -->
    <template v-else-if="isSimplifiedFlow && !isEditing">
      <div class="text-center mb-2">
        <p class="text-surface-700 dark:text-surface-300 font-medium">
          {{ messages.title }}
        </p>
        <p class="text-sm text-surface-500 dark:text-surface-400">
          {{ messages.subtitle }}
        </p>
      </div>

      <Message v-if="error" severity="error" :closable="false" class="mb-2">
        {{ error }}
      </Message>

      <!-- Show pre-filled data from announcement -->
      <div class="bg-surface-50 dark:bg-surface-800 rounded-xl p-4 mb-4">
        <p class="text-sm text-surface-500 dark:text-surface-400 mb-3">
          Ces informations seront ajoutées à votre profil :
        </p>
        <div class="flex flex-col gap-2 text-sm">
          <div class="flex items-center gap-2">
            <i class="pi pi-check text-green-500" />
            <span class="text-surface-900 dark:text-surface-0">Cherche un groupe</span>
          </div>
          <div class="flex items-center gap-2">
            <i class="pi pi-check text-green-500" />
            <span class="text-surface-900 dark:text-surface-0">{{ selectedInstrument?.musician_name }}</span>
          </div>
          <div class="flex items-center gap-2">
            <i class="pi pi-check text-green-500" />
            <span class="text-surface-900 dark:text-surface-0">{{ selectedStyles.map(s => s.name).join(', ') }}</span>
          </div>
        </div>
      </div>

      <!-- Only ask for skill level -->
      <div class="flex flex-col gap-2">
        <label for="simplifiedSkillLevel" class="font-medium text-surface-900 dark:text-surface-0">
          Quel est votre niveau en {{ selectedInstrument?.musician_name }} ?
        </label>
        <Select
          id="simplifiedSkillLevel"
          v-model="simplifiedSkillLevel"
          :options="skillLevelOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Sélectionnez votre niveau"
          :disabled="isSaving"
          class="w-full"
        />
      </div>

      <div class="flex justify-between pt-4">
        <Button
          label="Passer cette étape"
          severity="secondary"
          text
          :disabled="isSaving"
          @click="handleSkip"
        />
        <Button
          label="Créer mon profil"
          icon="pi pi-check"
          :loading="isSaving"
          :disabled="!simplifiedSkillLevel"
          @click="handleSave"
        />
      </div>
    </template>

    <!-- No profile or editing - Full form view -->
    <template v-else>
      <div class="text-center mb-2">
        <p class="text-surface-700 dark:text-surface-300 font-medium">
          {{ messages.title }}
        </p>
        <p class="text-sm text-surface-500 dark:text-surface-400">
          {{ messages.subtitle }}
        </p>
      </div>

      <Message v-if="error" severity="error" :closable="false" class="mb-2">
        {{ error }}
      </Message>

      <div class="flex flex-col gap-4">
        <!-- Availability status - Always visible -->
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

        <!-- Instruments - Expandable section -->
        <Fieldset legend="Ajouter des instruments" :toggleable="true" :collapsed="instrumentsCollapsed">
          <div v-if="instruments.length === 0" class="text-surface-500 dark:text-surface-400 text-sm py-2">
            Aucun instrument ajouté
          </div>

          <div v-else class="flex flex-col gap-3 mb-3">
            <div
              v-for="(instrument, index) in instruments"
              :key="index"
              class="flex items-center gap-3 p-3 rounded-lg bg-surface-100 dark:bg-surface-700"
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

          <Button
            icon="pi pi-plus"
            label="Ajouter un instrument"
            severity="secondary"
            size="small"
            :disabled="isSaving"
            @click="addInstrument"
          />
        </Fieldset>

        <!-- Styles - Expandable section -->
        <Fieldset legend="Ajouter des styles musicaux" :toggleable="true" :collapsed="stylesCollapsed">
          <MultiSelect
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
        </Fieldset>
      </div>

      <div class="flex justify-between pt-4">
        <Button
          label="Passer cette étape"
          severity="secondary"
          text
          :disabled="isSaving"
          @click="handleSkip"
        />
        <Button
          :label="musicianProfileStore.profile ? 'Enregistrer et continuer' : 'Créer et continuer'"
          icon="pi pi-check"
          :loading="isSaving"
          @click="handleSave"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Fieldset from 'primevue/fieldset'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed, ref, watch } from 'vue'
import { useInstrumentStore } from '../../../store/attribute/instrument.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'

const props = defineProps({
  announcementType: {
    type: String,
    default: null
  },
  // For simplified flow when musician searches for band
  selectedInstrument: {
    type: Object,
    default: null
  },
  selectedStyles: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['saved', 'skipped'])

const musicianProfileStore = useMusicianProfileStore()
const instrumentStore = useInstrumentStore()
const styleStore = useStyleStore()

const isEditing = ref(false)
const isSaving = ref(false)
const error = ref('')

const availabilityStatus = ref(null)
const instruments = ref([])
const selectedStyleIds = ref([])

const instrumentsCollapsed = ref(true)
const stylesCollapsed = ref(true)

// Simplified flow: musician searching for band - only need skill level
const isSimplifiedFlow = computed(() => props.announcementType === 'band' && props.selectedInstrument)
const simplifiedSkillLevel = ref(null)

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

const messages = computed(() => {
  if (props.announcementType === 'band') {
    if (isSimplifiedFlow.value) {
      return {
        title: 'Créez votre profil musicien en un clic',
        subtitle: 'Votre annonce contient déjà toutes les infos, il ne manque que votre niveau !',
        cta: 'Un profil complet augmente vos chances d\'être contacté'
      }
    }
    return {
      title: 'Complétez votre profil musicien',
      subtitle: 'Montrez aux groupes vos compétences et votre expérience',
      cta: 'Un profil complet augmente vos chances d\'être contacté'
    }
  }
  return {
    title: 'Présentez votre groupe',
    subtitle: 'Les musiciens verront avec qui ils pourraient jouer',
    cta: 'Un profil complet inspire confiance aux candidats'
  }
})

function getAvailabilityLabel(value) {
  const option = availabilityOptions.find(o => o.value === value)
  return option?.label || value
}

function getSkillLevelLabel(value) {
  const option = skillLevelOptions.find(o => o.value === value)
  return option?.label || value
}

// Initialize form when entering edit mode
watch(isEditing, (editing) => {
  if (editing) {
    initForm()
  }
})

// Initialize form when profile is loaded and no existing profile (create mode)
watch(() => musicianProfileStore.profile, (profile) => {
  if (!profile && !isEditing.value) {
    initForm()
  }
}, { immediate: true })

function initForm() {
  error.value = ''

  if (musicianProfileStore.profile) {
    availabilityStatus.value = musicianProfileStore.profile.availability_status || null

    instruments.value = (musicianProfileStore.profile.instruments || []).map((i) => ({
      instrumentId: i.instrument_id,
      skillLevel: i.skill_level
    }))

    selectedStyleIds.value = (musicianProfileStore.profile.styles || []).map((s) => s.id)

    // Expand sections if they have data
    instrumentsCollapsed.value = instruments.value.length === 0
    stylesCollapsed.value = selectedStyleIds.value.length === 0
  } else {
    availabilityStatus.value = null
    instruments.value = []
    selectedStyleIds.value = []
    instrumentsCollapsed.value = true
    stylesCollapsed.value = true
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

function handleContinue() {
  musicianProfileStore.markProfileStepCompleted()
  emit('skipped')
}

function handleSkip() {
  musicianProfileStore.markProfileStepCompleted()
  emit('skipped')
}

async function handleSave() {
  error.value = ''
  isSaving.value = true

  let data

  if (isSimplifiedFlow.value && !isEditing.value) {
    // Simplified flow: use announcement data + selected skill level
    data = {
      availability_status: 'looking_for_band',
      instruments: [{
        instrument_id: props.selectedInstrument.id,
        skill_level: simplifiedSkillLevel.value
      }],
      style_ids: props.selectedStyles.map(s => s.id)
    }
  } else {
    // Full form flow
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

    data = {
      availability_status: availabilityStatus.value || null,
      instruments: validInstruments.map((i) => ({
        instrument_id: i.instrumentId,
        skill_level: i.skillLevel
      })),
      style_ids: selectedStyleIds.value
    }
  }

  try {
    if (musicianProfileStore.profile) {
      await musicianProfileStore.updateProfile(data)
    } else {
      await musicianProfileStore.createProfile(data)
    }
    musicianProfileStore.markProfileStepCompleted()
    isEditing.value = false
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
</script>
