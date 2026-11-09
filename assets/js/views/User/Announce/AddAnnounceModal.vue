<template>
  <Dialog
    v-model:visible="visible"
    modal
    :closable="!userAnnounceStore.isSaving"
    :style="dialogStyle"
    :pt="dialogPt"
    @hide="reset"
  >
    <template #header>
      <div class="flex items-center gap-3">
        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30">
          <i class="pi pi-megaphone text-primary-600 dark:text-primary-400 text-lg" />
        </div>
        <div>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 m-0">
            {{ t('profile.announce.add.title') }}
          </h2>
          <p class="text-sm text-surface-500 dark:text-surface-400 m-0">
            {{ t('profile.announce.add.subtitle') }}
          </p>
        </div>
      </div>
    </template>

    <div v-if="isSuccess" class="flex flex-col items-center justify-center py-8">
      <i class="pi pi-check-circle text-6xl text-green-500 mb-4" />
      <p class="text-lg font-medium text-surface-900 dark:text-surface-0">
        {{ t('profile.announce.add.success') }}
      </p>
    </div>

    <Stepper v-else v-model:value="currentStep" :linear="!hasInitialValues">
      <!-- Six labelled steps do not fit a phone: where the member is, in words, over a six-part bar. -->
      <div v-if="isBelowMd" class="flex flex-col gap-2">
        <p class="m-0 text-sm font-medium text-surface-700 dark:text-surface-200" aria-live="polite">
          {{ t('profile.announce.add.step_progress', { current: Number(currentStep), total: stepLabels.length, label: t(stepLabels[Number(currentStep) - 1]) }) }}
        </p>
        <div :class="['grid gap-1', stepLabels.length === 6 ? 'grid-cols-6' : 'grid-cols-5']" aria-hidden="true">
          <span
            v-for="(label, index) in stepLabels"
            :key="label"
            :class="['h-1 rounded-full', index < Number(currentStep) ? 'bg-primary' : 'bg-surface-200 dark:bg-surface-700']"
          />
        </div>
      </div>
      <StepList v-else>
        <Step value="1">{{ t('profile.announce.add.step_type') }}</Step>
        <Step value="2">{{ t('profile.announce.add.step_instrument') }}</Step>
        <Step value="3">{{ t('profile.announce.add.step_styles') }}</Step>
        <Step value="4">{{ t('profile.announce.add.step_location') }}</Step>
        <Step v-if="!isVisitor" value="5">{{ t('profile.announce.add.step_profile') }}</Step>
        <Step :value="noteStep">{{ t('profile.announce.add.step_note') }}</Step>
      </StepList>

      <StepPanels>
        <StepPanel v-slot="{ activateCallback }" value="1">
          <div class="flex flex-col gap-4 py-4">
            <p class="text-surface-700 dark:text-surface-300 font-medium">
              {{ t('profile.announce.add.type_question') }}
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
                  <p class="font-semibold text-surface-900 dark:text-surface-0">{{ t('profile.announce.add.type_band') }}</p>
                  <p class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.announce.add.type_band_hint') }}</p>
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
                  <p class="font-semibold text-surface-900 dark:text-surface-0">{{ t('profile.announce.add.type_musician') }}</p>
                  <p class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.announce.add.type_musician_hint') }}</p>
                </div>
              </div>
            </div>
          </div>
          <div class="flex justify-end pt-4">
            <Button
              :label="t('profile.announce.add.next')"
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
              {{ selectedType === 'band' ? t('profile.announce.add.instrument_question_band') : t('profile.announce.add.instrument_question_musician') }}
            </p>
            <Select
              v-model="selectedInstrument"
              :options="instrumentStore.instruments"
              filter
              optionLabel="musician_name"
              :placeholder="t('profile.announce.add.instrument_placeholder')"
              class="w-full"
            />
          </div>
          <div class="flex justify-between pt-4">
            <Button
              :label="t('profile.announce.add.previous')"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback('1')"
            />
            <Button
              :label="t('profile.announce.add.next')"
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
              {{ selectedType === 'band' ? t('profile.announce.add.styles_question_band') : t('profile.announce.add.styles_question_musician') }}
            </p>
            <MultiSelect
              v-model="selectedStyles"
              :options="styleStore.styles"
              optionLabel="name"
              filter
              :placeholder="t('profile.announce.add.styles_placeholder')"
              class="w-full"
              display="chip"
              :selectionLimit="10"
            />
            <small class="text-surface-500 dark:text-surface-400">
              {{ t('profile.announce.add.styles_count', { count: selectedStyles.length }) }}
            </small>
          </div>
          <div class="flex justify-between pt-4">
            <Button
              :label="t('profile.announce.add.previous')"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback('2')"
            />
            <Button
              :label="t('profile.announce.add.next')"
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
              {{ t('profile.announce.add.location_question') }}
            </p>

            <AutoComplete
              v-model="selectedLocation"
              :suggestions="locationSuggestions"
              optionLabel="name"
              :placeholder="t('profile.announce.add.location_placeholder')"
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
              {{ t('profile.announce.add.location_hint') }}
            </Message>
          </div>
          <div class="flex justify-between pt-4">
            <Button
              :label="t('profile.announce.add.previous')"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback('3')"
            />
            <Button
              :label="t('profile.announce.add.next')"
              icon="pi pi-arrow-right"
              iconPos="right"
              :disabled="!selectedLocation || typeof selectedLocation !== 'object'"
              @click="handleLocationNext(activateCallback)"
            />
          </div>
        </StepPanel>

        <!-- It edits the member's musician profile, so a visitor meets it after signing in (#1306). -->
        <StepPanel v-if="!isVisitor" v-slot="{ activateCallback }" value="5">
          <MusicianProfileStepContent
            :announcement-type="selectedType"
            :selected-instrument="selectedInstrument"
            :selected-styles="selectedStyles"
            @saved="activateCallback('6')"
            @skipped="activateCallback('6')"
          />
        </StepPanel>

        <StepPanel v-slot="{ activateCallback }" :value="noteStep">
          <div class="flex flex-col gap-4 py-4">
            <p class="text-surface-700 dark:text-surface-300 font-medium">
              {{ t('profile.announce.add.note_question') }}
            </p>
            <Textarea
              v-model="note"
              rows="4"
              :placeholder="t('profile.announce.add.note_placeholder')"
              class="w-full"
            />

            <div class="flex flex-col gap-2">
              <p id="announce-languages-label" class="text-surface-700 dark:text-surface-300 font-medium m-0">
                {{ t('profile.announce.add.languages_label') }}
              </p>
              <MultiSelect
                v-model="selectedLanguages"
                :options="languageChoices"
                optionLabel="name"
                optionValue="code"
                display="chip"
                :placeholder="t('profile.announce.add.languages_placeholder')"
                aria-labelledby="announce-languages-label"
                class="w-full"
              />
              <Message v-if="selectedLanguages.length === 0" severity="error" size="small" variant="simple">
                {{ t('profile.announce.add.languages_required') }}
              </Message>
              <small class="text-surface-500 dark:text-surface-400">
                {{ t('profile.announce.add.languages_hint') }}
              </small>
            </div>

            <div class="bg-surface-100 dark:bg-surface-800 rounded-xl p-4 mt-2">
              <p class="font-semibold text-surface-900 dark:text-surface-0 mb-3">{{ t('profile.announce.add.summary') }}</p>
              <div class="flex flex-col gap-2 text-sm">
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">{{ t('profile.announce.add.summary_type') }}</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedType === 'band' ? t('profile.announce.add.summary_type_band') : t('profile.announce.add.summary_type_musician') }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">{{ t('profile.announce.add.summary_instrument') }}</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedInstrument?.musician_name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">{{ t('profile.announce.add.summary_styles') }}</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedStyles.map(s => s.name).join(', ') }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">{{ t('profile.announce.add.summary_location') }}</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ selectedLocation?.fullName }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-surface-500 dark:text-surface-400">{{ t('profile.announce.add.summary_languages') }}</span>
                  <span class="text-surface-900 dark:text-surface-0">{{ languageListLabel(selectedLanguages) }}</span>
                </div>
              </div>
            </div>
          </div>
          <Message v-if="userAnnounceStore.saveError" severity="error" :closable="false" class="mt-4">
            {{ userAnnounceStore.saveError }}
          </Message>
          <div class="flex justify-between pt-4">
            <Button
              :label="t('profile.announce.add.previous')"
              icon="pi pi-arrow-left"
              severity="secondary"
              text
              @click="activateCallback(isVisitor ? '4' : '5')"
            />
            <Button
              :label="t('profile.announce.add.submit')"
              icon="pi pi-check"
              :loading="userAnnounceStore.isSaving"
              :disabled="selectedLanguages.length === 0"
              @click="save"
            />
          </div>
        </StepPanel>
      </StepPanels>
    </Stepper>

    <template #footer>
      <div v-if="isSuccess" class="flex justify-end w-full">
        <Button :label="t('profile.announce.add.close')" @click="visible = false" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
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
import { useI18n } from 'vue-i18n'
import geocodingApi from '../../../api/geocoding.js'
import MusicianProfileStepContent from '../../../components/User/Announce/MusicianProfileStepContent.vue'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { useUserAnnounceStore } from '../../../store/announce/userAnnounce.js'
import { useInstrumentStore } from '../../../store/attribute/instrument.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import {
  isAnnounceLanguage,
  languageListLabel,
  languageOptions
} from '../../../utils/announceLanguages.js'

const props = defineProps({
  initialType: { type: String, default: null },
  initialInstrument: { type: Object, default: null },
  initialStyles: { type: Array, default: () => [] },
  initialLocation: { type: Object, default: null },
  /** Language codes the search asked for, welcomed on top of the author's own. */
  initialLanguages: { type: Array, default: () => [] },
  initialNote: { type: String, default: '' },
  /** Reopened from what a visitor filled in before signing in (#1306): its languages are taken as they are. */
  isResumed: { type: Boolean, default: false }
})

// `sign-in-required` carries the form's values when a visitor clicks « Publier »: nothing is sent for them.
const emit = defineEmits(['created', 'sign-in-required'])
const visible = defineModel('visible', { type: Boolean, default: false })
const { t, locale } = useI18n()

const userAnnounceStore = useUserAnnounceStore()
const instrumentStore = useInstrumentStore()
const styleStore = useStyleStore()
const musicianProfileStore = useMusicianProfileStore()
const userSecurityStore = useUserSecurityStore()

const currentStep = ref('1')
const STEP_LABELS = [
  'profile.announce.add.step_type',
  'profile.announce.add.step_instrument',
  'profile.announce.add.step_styles',
  'profile.announce.add.step_location',
  'profile.announce.add.step_profile',
  'profile.announce.add.step_note'
]
const PROFILE_STEP_LABEL = 'profile.announce.add.step_profile'
const isVisitor = computed(
  () => !userSecurityStore.isAuthenticatedLoading && !userSecurityStore.isAuthenticated
)
const stepLabels = computed(() =>
  isVisitor.value ? STEP_LABELS.filter((label) => label !== PROFILE_STEP_LABEL) : STEP_LABELS
)
// The stepper shows a step's value as its number: without the profile step, the note is a visitor's fifth.
const noteStep = computed(() => (isVisitor.value ? '5' : '6'))

// A screen of its own on a phone, where a 95vw dialog cut the steps off at « St ».
const isBelowMd = useIsBelowMd()
const dialogStyle = computed(() =>
  isBelowMd.value
    ? { width: '100vw', height: '100dvh', maxHeight: '100dvh' }
    : { width: '95vw', maxWidth: '750px' }
)
const dialogPt = computed(() => ({
  root: { class: isBelowMd.value ? '!m-0 !max-w-full !rounded-none' : '' },
  header: { class: 'pb-0 border-0' },
  content: { class: 'pt-4' }
}))
const isSuccess = ref(false)

const selectedType = ref(null)
const selectedInstrument = ref(null)
const selectedStyles = ref([])
const selectedLocation = ref(null)
const note = ref('')
const selectedLanguages = ref([])
const languageChoices = computed(() => languageOptions())

// Location search
const locationSuggestions = ref([])

const hasInitialValues = computed(() => {
  return (
    props.initialType ||
    props.initialInstrument ||
    props.initialStyles?.length > 0 ||
    props.initialLocation
  )
})

onMounted(async () => {
  await Promise.all([instrumentStore.loadInstruments(), styleStore.loadStyles()])
})

// Initialize from props when modal opens. Immediate, because the search page mounts it already open.
watch(
  visible,
  async (isVisible) => {
    if (isVisible) {
      initializeFromProps()
      if (isVisitor.value) return
      // Load user profile (404 means no profile, which is fine)
      try {
        await musicianProfileStore.loadMyProfile()
      } catch {
        // No profile exists, that's OK
      }
      // Asked before the profile was in: a member who has one goes straight to the note.
      if (currentStep.value === '5') currentStep.value = getFirstMissingStep()
    }
  },
  { immediate: true }
)

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
  note.value = props.initialNote
  selectedLanguages.value = props.isResumed ? [...props.initialLanguages] : startingLanguages()

  // Find the first step with missing data
  currentStep.value = getFirstMissingStep()
}

function getFirstMissingStep() {
  if (!selectedType.value) return '1'
  if (!selectedInstrument.value) return '2'
  if (selectedStyles.value.length === 0) return '3'
  if (!selectedLocation.value) return '4'
  // Skip profile step if user has profile or already completed this step; a visitor meets it after signing in
  if (
    isVisitor.value ||
    musicianProfileStore.profile ||
    musicianProfileStore.profileStepCompleted
  ) {
    return noteStep.value
  }
  return '5'
}

// The author's own language, as the server picks for a client that sends none, plus what the search asked for.
function startingLanguages() {
  const authorLocale = userSecurityStore.userProfile?.locale ?? locale.value
  const own = isAnnounceLanguage(authorLocale) ? [authorLocale] : []
  return [...new Set([...own, ...props.initialLanguages])]
}

function handleLocationNext(activateCallback) {
  // Always show profile step - component handles display mode based on profile existence. A visitor's 5 is the note.
  activateCallback('5')
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
  if (isVisitor.value) {
    emit('sign-in-required', {
      type: selectedType.value,
      instrument: selectedInstrument.value,
      styles: selectedStyles.value,
      location: selectedLocation.value,
      note: note.value,
      languages: selectedLanguages.value
    })
    return
  }

  const success = await userAnnounceStore.createAnnounce({
    type: selectedType.value,
    instrument: selectedInstrument.value,
    styles: selectedStyles.value,
    location: {
      name: selectedLocation.value.fullName,
      latitude: selectedLocation.value.latitude,
      longitude: selectedLocation.value.longitude
    },
    note: note.value,
    languages: selectedLanguages.value
  })

  if (success) {
    trackUmamiEvent('announce-create', { type: selectedType.value })
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
  selectedLanguages.value = []
  locationSuggestions.value = []
  userAnnounceStore.clearError()
  musicianProfileStore.resetProfileStepCompleted()
}
</script>
