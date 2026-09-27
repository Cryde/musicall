<template>
  <div class="flex flex-col gap-3">
    <p v-if="step" class="m-0 text-surface-700 dark:text-surface-300">
      3 questions, et on vous montre les profils qui correspondent.
    </p>

    <!-- The questions, one at a time -->
    <section
      v-if="step"
      class="flex flex-col gap-5 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5 lg:p-6"
      :aria-label="`Question ${stepNumber} sur ${GUIDED_STEPS.length}`"
    >
      <!-- Each answered step is a way back to its question. -->
      <nav aria-label="Étapes de la recherche">
        <ol class="m-0 p-0 list-none grid grid-cols-3 gap-2">
          <li v-for="item in GUIDED_STEPS" :key="item">
            <component
              :is="canOpen(item) ? 'button' : 'span'"
              :type="canOpen(item) ? 'button' : undefined"
              :aria-current="item === step ? 'step' : undefined"
              class="flex w-full flex-col gap-1.5 text-left"
              :class="canOpen(item) && 'cursor-pointer group'"
              @click="canOpen(item) && edit(item)"
            >
              <span
                class="flex items-center gap-1.5 text-xs font-semibold"
                :class="item === step ? 'text-surface-900 dark:text-surface-0' : 'text-surface-600 dark:text-surface-300 group-hover:text-primary'"
              >
                <i v-if="answered.includes(item) && item !== step" class="pi pi-check text-[0.65rem] text-teal-700 dark:text-teal-300" aria-hidden="true" />
                {{ STEP_LABELS[item] }}
                <span v-if="answered.includes(item) && item !== step" class="sr-only">(modifier)</span>
              </span>
              <span
                class="h-1 rounded-full"
                :class="answered.includes(item) || item === step ? 'bg-teal-500' : 'bg-surface-200 dark:bg-surface-700'"
                aria-hidden="true"
              />
            </component>
          </li>
        </ol>
      </nav>

      <GuidedChips v-if="chips.length > 0" :chips="chips" @edit="edit" />

      <h2 ref="question" tabindex="-1" class="m-0 text-xl font-semibold text-surface-900 dark:text-surface-0 outline-none">
        {{ stepQuestion(step, lookingForBand) }}
      </h2>

      <!-- 1. Instrument -->
      <template v-if="step === 'instrument'">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2.5">
          <Button
            v-for="item in quickInstruments"
            :key="item.id"
            :label="item.musician_name"
            severity="secondary"
            outlined
            class="min-h-12"
            @click="answerInstrument(item)"
          />
          <Select
            :model-value="otherInstrument"
            :options="instruments"
            option-label="musician_name"
            filter
            placeholder="Autre instrument"
            aria-label="Autre instrument"
            class="min-h-12 col-span-2 md:col-span-1"
            @update:model-value="answerInstrument"
          />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <button type="button" class="text-sm font-semibold text-primary hover:underline cursor-pointer" @click="$emit('toggle-type')">
            {{ lookingForBand ? 'Je cherche plutôt un musicien' : 'Je cherche plutôt à rejoindre un groupe' }}
            <i class="pi pi-arrow-right text-xs ml-1" aria-hidden="true" />
          </button>
          <Button label="Peu importe" severity="secondary" text @click="answerInstrument(null)" />
        </div>
      </template>

      <!-- 2. City and radius -->
      <template v-else-if="step === 'location'">
        <div class="flex flex-col gap-2">
          <label for="guided-city" class="sr-only">Ville</label>
          <CityAutoComplete v-model="draftLocation" input-id="guided-city" placeholder="Ville" />
          <button
            type="button"
            class="self-start inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline cursor-pointer disabled:opacity-60"
            :disabled="isLocating"
            @click="useMyLocation"
          >
            <i :class="isLocating ? 'pi pi-spin pi-spinner' : 'pi pi-map-marker'" aria-hidden="true" />
            Utiliser ma position
          </button>
          <small v-if="locationError" role="alert" class="text-red-700 dark:text-red-400">{{ locationError }}</small>
        </div>
        <div class="flex flex-col gap-2">
          <span id="guided-radius-label" class="text-sm text-surface-700 dark:text-surface-300">Jusqu'à quelle distance ?</span>
          <SelectButton
            v-model="draftRadius"
            :options="radiusOptions"
            option-label="label"
            option-value="value"
            :allow-empty="false"
            aria-labelledby="guided-radius-label"
          />
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <Button label="Retour" icon="pi pi-arrow-left" severity="secondary" outlined @click="goBack" />
          <Button label="Continuer" class="w-full sm:w-auto order-first sm:order-none" :disabled="!hasDraftLocation" @click="answerLocation(true)" />
          <Button label="Peu importe" severity="secondary" text @click="answerLocation(false)" />
        </div>
      </template>

      <!-- 3. Styles -->
      <template v-else-if="step === 'styles'">
        <span class="-mt-3 text-sm text-surface-600 dark:text-surface-300">Plusieurs choix possibles</span>
        <div class="flex flex-wrap gap-2" role="group" aria-label="Styles">
          <ToggleButton
            v-for="style in quickStyles"
            :key="style.id"
            :model-value="isDraftStyle(style)"
            :on-label="style.name"
            :off-label="style.name"
            on-icon="pi pi-check"
            @update:model-value="toggleStyle(style)"
          />
        </div>
        <MultiSelect
          v-model="otherStyles"
          :options="otherStyleOptions"
          option-label="name"
          filter
          placeholder="Autres styles"
          aria-label="Autres styles"
          display="chip"
          class="w-full md:w-80"
        />
        <div class="flex flex-wrap items-center gap-3">
          <Button label="Retour" icon="pi pi-arrow-left" severity="secondary" outlined @click="goBack" />
          <Button :label="`Voir les ${sought}`" class="w-full sm:w-auto order-first sm:order-none" @click="answerStyles(true)" />
          <Button label="Tous styles" severity="secondary" text @click="answerStyles(false)" />
        </div>
      </template>
    </section>

    <!-- Every question answered: what the search is, and a way to change it -->
    <section
      v-else
      class="flex flex-col gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-4 lg:p-5"
      aria-labelledby="guided-summary-title"
    >
      <div class="flex items-center justify-between gap-3">
        <h2 id="guided-summary-title" class="m-0 text-lg font-semibold text-surface-900 dark:text-surface-0">Vos {{ sought }}</h2>
        <Button label="Modifier" icon="pi pi-pencil" severity="secondary" text size="small" @click="edit('instrument')" />
      </div>
      <GuidedChips :chips="chips" @edit="edit" />
    </section>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import ToggleButton from 'primevue/togglebutton'
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import geocodingApi from '../../../api/geocoding.js'
import {
  DEFAULT_RADIUS,
  GUIDED_STEPS,
  locationLabel,
  nextStep,
  QUICK_INSTRUMENT_SLUGS,
  QUICK_STYLE_SLUGS,
  quickPicks,
  RADIUS_OPTIONS,
  STEP_LABELS,
  soughtLabel,
  stepQuestion
} from '../../../utils/guidedSearch.js'
import CityAutoComplete from '../../Global/CityAutoComplete.vue'
import GuidedChips from './GuidedChips.vue'

/**
 * The guided search's questions (#1084), one at a time, each answering part of the search the page
 * runs. `answer` asks the page to search again; `complete` says whether it was the last question.
 */
const props = defineProps({
  instruments: { type: Array, required: true },
  styles: { type: Array, required: true },
  lookingForBand: { type: Boolean, default: false },
  /** The questions the URL already answered, skipped on arrival. */
  initiallyAnswered: { type: Array, default: () => [] }
})

const emit = defineEmits(['answer', 'toggle-type'])

const instrument = defineModel('instrument', { type: Object, default: null })
const location = defineModel('location', { type: Object, default: null })
const radius = defineModel('radius', { type: Number, default: null })
const selectedStyles = defineModel('selectedStyles', { type: Array, default: () => [] })

const answered = ref([...props.initiallyAnswered])
const editing = ref(null)
const step = computed(() => editing.value ?? nextStep(answered.value))
const stepNumber = computed(() => GUIDED_STEPS.indexOf(step.value) + 1)
const questionHeading = useTemplateRef('question')

const radiusOptions = RADIUS_OPTIONS.map((km) => ({ label: `${km} km`, value: km }))
const quickInstruments = computed(() => quickPicks(props.instruments, QUICK_INSTRUMENT_SLUGS))
const otherInstrument = computed(() =>
  instrument.value && !quickInstruments.value.some((item) => item.id === instrument.value.id)
    ? instrument.value
    : null
)
const quickStyles = computed(() => quickPicks(props.styles, QUICK_STYLE_SLUGS))
const otherStyleOptions = computed(() =>
  props.styles.filter((style) => !QUICK_STYLE_SLUGS.includes(style.slug))
)
const sought = computed(() =>
  soughtLabel({ lookingForBand: props.lookingForBand, instrument: instrument.value })
)

// What the step being answered holds before it is confirmed.
const draftLocation = ref(location.value)
const draftRadius = ref(radius.value ?? DEFAULT_RADIUS)
const draftStyles = ref([...selectedStyles.value])
const hasDraftLocation = computed(
  () => draftLocation.value !== null && typeof draftLocation.value === 'object'
)
const isLocating = ref(false)
const locationError = ref(null)

const otherStyles = computed({
  get: () => draftStyles.value.filter((style) => !QUICK_STYLE_SLUGS.includes(style.slug)),
  set: (others) => {
    draftStyles.value = [
      ...draftStyles.value.filter((style) => QUICK_STYLE_SLUGS.includes(style.slug)),
      ...others
    ]
  }
})

const chips = computed(() =>
  GUIDED_STEPS.filter((item) => answered.value.includes(item) && item !== step.value).map(
    (item) => ({
      step: item,
      label: chipLabel(item)
    })
  )
)

function chipLabel(item) {
  if (item === 'instrument') return instrument.value?.musician_name ?? 'Tous les instruments'
  if (item === 'location') return locationLabel(location.value, radius.value) ?? 'Partout'
  return selectedStyles.value.length > 0
    ? selectedStyles.value.map((style) => style.name).join(', ')
    : 'Tous styles'
}

// Moving to another question moves the reader to it, so a keyboard or screen reader follows along.
watch(step, async (current, previous) => {
  if (!current || current === previous) return
  draftLocation.value = location.value
  draftRadius.value = radius.value ?? DEFAULT_RADIUS
  draftStyles.value = [...selectedStyles.value]
  await nextTick()
  questionHeading.value?.focus()
})

function done(item) {
  if (!answered.value.includes(item)) answered.value = [...answered.value, item]
  editing.value = null
  emit('answer', { complete: nextStep(answered.value) === null })
}

function edit(item) {
  editing.value = item
}

// A question the member already answered, other than the one on screen, can be opened again.
function canOpen(item) {
  return answered.value.includes(item) && item !== step.value
}

// The previous question, its answer still selected, so going back and forth costs nothing.
function goBack() {
  const previous = GUIDED_STEPS[GUIDED_STEPS.indexOf(step.value) - 1]
  if (previous) edit(previous)
}

function answerInstrument(value) {
  instrument.value = value
  done('instrument')
}

function answerLocation(withCity) {
  location.value = withCity ? draftLocation.value : null
  radius.value = withCity ? draftRadius.value : null
  done('location')
}

function isDraftStyle(style) {
  return draftStyles.value.some((item) => item.id === style.id)
}

function toggleStyle(style) {
  draftStyles.value = isDraftStyle(style)
    ? draftStyles.value.filter((item) => item.id !== style.id)
    : [...draftStyles.value, style]
}

function answerStyles(withStyles) {
  selectedStyles.value = withStyles ? [...draftStyles.value] : []
  done('styles')
}

function useMyLocation() {
  if (!navigator.geolocation) {
    locationError.value = 'Votre navigateur ne donne pas accès à votre position.'
    return
  }
  isLocating.value = true
  locationError.value = null
  navigator.geolocation.getCurrentPosition(
    async ({ coords }) => {
      try {
        draftLocation.value = await geocodingApi.reverseGeocode(coords.latitude, coords.longitude)
        if (!draftLocation.value)
          locationError.value = 'Impossible de trouver votre ville, saisissez-la.'
      } catch {
        locationError.value = 'Impossible de trouver votre ville, saisissez-la.'
      } finally {
        isLocating.value = false
      }
    },
    () => {
      locationError.value = "Votre position n'est pas accessible, saisissez votre ville."
      isLocating.value = false
    },
    { timeout: 10000 }
  )
}
</script>
