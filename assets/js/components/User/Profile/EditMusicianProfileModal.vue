<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('profile.musician.edit.title')"
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
          {{ t('profile.musician.edit.availability') }}
        </label>
        <Select
          id="availabilityStatus"
          v-model="availabilityStatus"
          :options="availabilityOptions"
          optionLabel="label"
          optionValue="value"
          :placeholder="t('profile.musician.edit.availability_placeholder')"
          :disabled="isSaving"
          class="w-full"
          showClear
        />
      </div>

      <!-- Instruments -->
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <label class="font-medium text-surface-900 dark:text-surface-0">
            {{ t('profile.musician.edit.instruments') }}
          </label>
          <Button
            icon="pi pi-plus"
            :label="t('profile.musician.edit.add')"
            severity="secondary"
            size="small"
            :disabled="isSaving"
            @click="addInstrument"
          />
        </div>

        <div v-if="instruments.length === 0" class="text-surface-500 dark:text-surface-400 text-sm py-2">
          {{ t('profile.musician.edit.no_instrument') }}
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
              :placeholder="t('profile.musician.edit.instrument_placeholder')"
              :disabled="isSaving"
              class="flex-1"
            />
            <Select
              v-model="instrument.skillLevel"
              :options="skillLevelOptions"
              optionLabel="label"
              optionValue="value"
              :placeholder="t('profile.musician.edit.level_placeholder')"
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
          {{ t('profile.musician.edit.styles') }}
        </label>
        <MultiSelect
          id="styles"
          v-model="selectedStyleIds"
          :options="styleOptions"
          optionLabel="name"
          optionValue="id"
          :placeholder="t('profile.musician.edit.styles_placeholder')"
          :disabled="isSaving"
          class="w-full"
          display="chip"
          filter
        />
      </div>

      <!-- Bands (#1313) -->
      <div class="flex flex-col gap-2" role="group" aria-labelledby="musician-bands-label">
        <div class="flex items-center justify-between">
          <span id="musician-bands-label" class="font-medium text-surface-900 dark:text-surface-0">
            {{ t('profile.musician.edit.bands') }}
          </span>
          <Button
            v-if="bands.length < MAX_BANDS"
            icon="pi pi-plus"
            :label="t('profile.musician.edit.add_band')"
            severity="secondary"
            size="small"
            :disabled="isSaving"
            @click="bands.push(emptyBand())"
          />
        </div>
        <p class="text-sm text-surface-500 dark:text-surface-400 m-0">
          {{ t('profile.musician.edit.bands_help', { max: MAX_BANDS }) }}
        </p>

        <div
          v-for="(band, index) in bands"
          :key="index"
          class="flex flex-col gap-2 p-3 rounded-lg bg-surface-50 dark:bg-surface-800"
        >
          <div class="flex items-center gap-2">
            <InputText
              v-model="band.name"
              :aria-label="t('profile.musician.edit.band_name_aria', { number: index + 1 })"
              :placeholder="t('profile.musician.edit.band_name_placeholder')"
              :disabled="isSaving"
              maxlength="80"
              class="flex-1 min-w-0"
            />
            <Button
              icon="pi pi-trash"
              severity="danger"
              text
              rounded
              size="small"
              :aria-label="t('profile.musician.edit.remove_band')"
              :disabled="isSaving"
              @click="bands.splice(index, 1)"
            />
          </div>
          <div class="grid gap-2 sm:grid-cols-2">
            <Select
              v-model="band.styleId"
              :options="styleOptions"
              optionLabel="name"
              optionValue="id"
              :aria-label="t('profile.musician.edit.band_style_aria', { number: index + 1 })"
              :placeholder="t('profile.musician.edit.band_style_placeholder')"
              :disabled="isSaving"
              filter
              showClear
            />
            <InputText
              v-model="band.role"
              :aria-label="t('profile.musician.edit.band_role_aria', { number: index + 1 })"
              :placeholder="t('profile.musician.edit.band_role_placeholder')"
              :disabled="isSaving"
              maxlength="80"
            />
          </div>
          <div class="flex flex-col sm:flex-row sm:items-center gap-2">
            <SelectButton
              v-model="band.current"
              :options="bandPeriodOptions"
              optionLabel="label"
              optionValue="value"
              :allowEmpty="false"
              :aria-label="t('profile.musician.edit.band_period_aria', { number: index + 1 })"
              :disabled="isSaving"
            />
            <InputText
              v-model="band.url"
              type="url"
              :aria-label="t('profile.musician.edit.band_url_aria', { number: index + 1 })"
              :placeholder="t('profile.musician.edit.band_url_placeholder')"
              :disabled="isSaving"
              maxlength="500"
              class="flex-1 min-w-0"
            />
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          :label="t('profile.musician.edit.cancel')"
          severity="secondary"
          :disabled="isSaving"
          @click="handleClose"
        />
        <Button
          :label="t('profile.musician.edit.save')"
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
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useInstrumentStore } from '../../../store/attribute/instrument.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { useMusicianProfileStore } from '../../../store/user/musicianProfile.js'
import {
  bandRowOf,
  bandsForm,
  bandsPayload,
  emptyBand,
  MAX_BANDS
} from '../../../utils/musicianBands.js'

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
const { t } = useI18n()

const musicianProfileStore = useMusicianProfileStore()
const instrumentStore = useInstrumentStore()
const styleStore = useStyleStore()

const availabilityStatus = ref(null)
const instruments = ref([])
const selectedStyleIds = ref([])
const bands = ref([])
const error = ref('')
const isSaving = ref(false)

const instrumentOptions = computed(() => instrumentStore.instruments || [])
const styleOptions = computed(() => styleStore.styles || [])

const availabilityOptions = computed(() => [
  { value: 'looking_for_band', label: t('profile.musician.availability.looking_for_band') },
  {
    value: 'available_for_sessions',
    label: t('profile.musician.availability.available_for_sessions')
  },
  {
    value: 'open_to_collaborations',
    label: t('profile.musician.availability.open_to_collaborations')
  },
  { value: 'not_available', label: t('profile.musician.availability.not_available') }
])

const skillLevelOptions = computed(() => [
  { value: 'beginner', label: t('profile.musician.skill_level.beginner') },
  { value: 'intermediate', label: t('profile.musician.skill_level.intermediate') },
  { value: 'advanced', label: t('profile.musician.skill_level.advanced') },
  { value: 'professional', label: t('profile.musician.skill_level.professional') }
])

const bandPeriodOptions = computed(() => [
  { value: true, label: t('profile.musician.edit.band_current') },
  { value: false, label: t('profile.musician.edit.band_past') }
])

const isVisible = computed({
  get: () => props.visible,
  set: (value) => emit('update:visible', value)
})

watch(
  () => props.visible,
  (visible) => {
    if (visible) {
      initForm()
      loadAttributesIfNeeded()
    }
  }
)

function initForm() {
  error.value = ''

  if (props.musicianProfile) {
    availabilityStatus.value = props.musicianProfile.availability_status || null

    instruments.value = (props.musicianProfile.instruments || []).map((i) => ({
      instrumentId: i.instrument_id,
      skillLevel: i.skill_level
    }))

    selectedStyleIds.value = (props.musicianProfile.styles || []).map((s) => s.id)
    bands.value = bandsForm(props.musicianProfile)
  } else {
    availabilityStatus.value = null
    instruments.value = []
    selectedStyleIds.value = []
    bands.value = []
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
    style_ids: selectedStyleIds.value,
    bands: bandsPayload(bands.value)
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
      error.value = e.violations.map(describeViolation).join('. ')
    } else {
      error.value = e.message || t('profile.musician.edit.error')
    }
  } finally {
    isSaving.value = false
  }
}

// Several bands can carry the same message, so a band's violation names its row.
function describeViolation(violation) {
  const row = bandRowOf(violation.propertyPath)
  return row
    ? t('profile.musician.edit.band_violation', { number: row, message: violation.message })
    : violation.message
}

onMounted(() => {
  loadAttributesIfNeeded()
})
</script>
