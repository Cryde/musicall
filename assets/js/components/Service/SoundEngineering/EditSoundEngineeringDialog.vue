<template>
  <ServiceFormDialog
    v-model:visible="isVisible"
    :header="t('profile.sound_engineer.form.title')"
    :is-loading="isLoading"
    :is-loaded="Boolean(form)"
    :is-saving="isSaving"
    :error="error"
    @show="handleShow"
    @hide="handleHide"
    @save="handleSave"
  >
    <template v-if="form">
      <ServiceBasicsEditor
        v-model:form="form"
        icon="pi pi-sliders-v"
        id-prefix="se"
        :specialities="specialityOptions"
        :headline-placeholder="t('profile.sound_engineer.form.headline_placeholder')"
        :description-placeholder="t('profile.sound_engineer.form.description_placeholder')"
        :start-year-label="t('profile.sound_engineer.form.start_year')"
        :disabled="isSaving"
      />

      <ServicePlacesEditor v-model:places="places" id-prefix="se" :disabled="isSaving">
        <label class="inline-flex items-center gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg font-medium text-surface-900 dark:text-surface-0">
          <Checkbox v-model="places.remote" binary :disabled="isSaving" />
          {{ t('profile.sound_engineer.form.place_remote') }}
        </label>
      </ServicePlacesEditor>

      <ServiceOffersEditor v-model:offers="offers" :unit-options="unitOptions" :disabled="isSaving" />

      <!-- Turnaround, revisions, formats, gear -->
      <ServiceFormSection icon="pi pi-clock" :title="t('profile.sound_engineer.form.section_delivery')" class="gap-5">
        <div class="grid gap-5 md:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label for="se-turnaround" :class="LABEL_CLASS">{{ t('profile.sound_engineer.form.turnaround') }}</label>
            <InputNumber fluid v-model="form.turnaround_days" input-id="se-turnaround" :min="1" :max="365" :suffix="` ${t('profile.sound_engineer.form.days_suffix')}`" :disabled="isSaving" />
          </div>
          <div class="flex flex-col gap-2">
            <label for="se-revisions" :class="LABEL_CLASS">{{ t('profile.sound_engineer.form.revisions') }}</label>
            <InputNumber fluid v-model="form.revisions_included" input-id="se-revisions" :min="0" :max="20" :disabled="isSaving" />
          </div>
        </div>
        <fieldset class="flex flex-col gap-2">
          <legend :class="[LABEL_CLASS, 'mb-2']">{{ t('profile.sound_engineer.form.formats') }}</legend>
          <div class="flex flex-wrap gap-x-5 gap-y-2">
            <label v-for="format in DELIVERY_FORMATS" :key="format" class="inline-flex items-center gap-2">
              <Checkbox v-model="form.formats" :value="format" :disabled="isSaving" />
              {{ t(FORMAT_KEYS[format]) }}
            </label>
          </div>
        </fieldset>
        <div class="flex flex-col gap-2">
          <label for="se-gear" :class="LABEL_CLASS">{{ t('profile.sound_engineer.form.gear') }}</label>
          <Textarea id="se-gear" v-model="gearText" rows="3" :placeholder="t('profile.sound_engineer.form.gear_placeholder')" :disabled="isSaving" aria-describedby="se-gear-help" />
          <small id="se-gear-help" class="text-surface-500 dark:text-surface-400">{{ t('profile.sound_engineer.form.gear_help', { max: MAX_GEAR }) }}</small>
        </div>
      </ServiceFormSection>

      <!-- « Avant, après », sent as soon as chosen -->
      <ServiceFormSection icon="pi pi-volume-up" :title="t('profile.sound_engineer_page.before_after')" class="gap-3">
        <p class="text-sm text-surface-600 dark:text-surface-400">{{ t('profile.sound_engineer.form.before_after_help') }}</p>
        <div class="grid gap-3 md:grid-cols-2">
          <div v-for="side in SIDES" :key="side" class="flex flex-col gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
            <p class="font-medium text-surface-900 dark:text-surface-0">{{ t(SIDE_KEYS[side]) }}</p>
            <template v-if="audioOf(side)">
              <p class="text-sm text-surface-600 dark:text-surface-400">
                {{ [audioOf(side).title, audioOf(side).credit, formatVoiceNoteDuration(audioOf(side).duration_seconds)].filter(Boolean).join(' · ') }}
              </p>
              <Button :label="t('profile.sound_engineer.form.remove_audio')" icon="pi pi-trash" severity="danger" text size="small" class="w-fit -ml-2" :loading="busySide === side" @click="removeAudio(side)" />
            </template>
            <InputText v-model="uploads[side].title" :maxlength="100" :placeholder="t('profile.sound_engineer.form.audio_title')" :aria-label="t('profile.sound_engineer.form.audio_title')" :disabled="busySide !== null" />
            <InputText v-model="uploads[side].credit" :maxlength="150" :placeholder="t('profile.sound_engineer.form.audio_credit')" :aria-label="t('profile.sound_engineer.form.audio_credit')" :disabled="busySide !== null" />
            <label class="w-fit">
              <span class="sr-only">{{ t('profile.sound_engineer.form.audio_file', { side: t(SIDE_KEYS[side]) }) }}</span>
              <input type="file" :accept="AUDIO_ACCEPT" class="text-sm" :disabled="busySide !== null" @change="(event) => uploadAudio(side, event)" />
            </label>
            <p v-if="busySide === side" class="text-sm text-surface-500 dark:text-surface-400"><i class="pi pi-spin pi-spinner mr-1" aria-hidden="true" />{{ t('profile.sound_engineer.form.audio_converting') }}</p>
          </div>
        </div>
      </ServiceFormSection>

      <ServiceWorksEditor v-model:works="form.works" :service-id="serviceId" @changed="markChanged" />

      <ServiceMediaEditor v-model:media="form.media" :service-id="serviceId" :url-label="t('profile.sound_engineer.form.media_url')" @changed="markChanged" />
    </template>
  </ServiceFormDialog>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import soundEngineerApi from '../../../api/service/soundEngineer.js'
import { useServiceForm } from '../../../composables/useServiceForm.js'
import {
  FORM_UNIT_KEYS,
  FORMAT_KEYS,
  SIDE_KEYS,
  SPECIALITY_KEYS
} from '../../../constants/soundEngineering.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { formatVoiceNoteDuration } from '../../../utils/chatVoiceNote.js'
import { offerRow, offersPayload } from '../../../utils/serviceOffers.js'
import { placesForm, placesPayload } from '../../../utils/servicePlaces.js'
import {
  DELIVERY_FORMATS,
  MAX_GEAR,
  OFFER_UNITS,
  parseGear,
  SPECIALITIES
} from '../../../utils/soundEngineerPage.js'
import ServiceBasicsEditor from '../Form/ServiceBasicsEditor.vue'
import ServiceFormDialog from '../Form/ServiceFormDialog.vue'
import ServiceFormSection from '../Form/ServiceFormSection.vue'
import ServiceMediaEditor from '../Form/ServiceMediaEditor.vue'
import ServiceOffersEditor from '../Form/ServiceOffersEditor.vue'
import ServicePlacesEditor from '../Form/ServicePlacesEditor.vue'
import ServiceWorksEditor from '../Works/ServiceWorksEditor.vue'

/** A sound engineer's form (#1410), from « Mes services » or their page. */
const props = defineProps({
  serviceId: { type: String, required: true }
})
/** `saved` once the form is saved; `changed` when a sound, a media or a work is written, each sent at once. */
const emit = defineEmits(['saved', 'changed'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const LABEL_CLASS = 'font-medium text-surface-900 dark:text-surface-0'
const SIDES = Object.keys(SIDE_KEYS)
/** Mirrors ServiceAudioConverter: what libmagic reads, the browser only filters the picker with it. */
const AUDIO_ACCEPT =
  '.wav,.aif,.aiff,.flac,.mp3,.m4a,audio/wav,audio/x-wav,audio/aiff,audio/x-aiff,audio/flac,audio/mpeg,audio/mp4,audio/x-m4a'

const { t } = useI18n()
const toast = useToast()
const styleStore = useStyleStore()

const busySide = ref(null)
const form = ref(null)
const places = ref(placesForm([]))
const offers = ref([])
const gearText = ref('')
const uploads = reactive({ before: { title: '', credit: '' }, after: { title: '', credit: '' } })

const { isLoading, isSaving, error, handleShow, handleSave, markChanged, handleHide } =
  useServiceForm({ load, save, emit, visible: isVisible })

const specialityOptions = computed(() =>
  SPECIALITIES.map((speciality) => ({ value: speciality, label: t(SPECIALITY_KEYS[speciality]) }))
)
const unitOptions = computed(() =>
  OFFER_UNITS.map((unit) => ({ value: unit, label: t(FORM_UNIT_KEYS[unit]) }))
)

function audioOf(side) {
  return form.value.before_after.find((audio) => audio.side === side) ?? null
}

async function load() {
  const [data] = await Promise.all([
    soundEngineerApi.getForm(props.serviceId),
    styleStore.styles?.length ? null : styleStore.loadStyles()
  ])
  form.value = data
  places.value = placesForm(data.places)
  offers.value = data.offers.map(offerRow)
  gearText.value = data.gear.join('\n')
}

function save() {
  return soundEngineerApi.saveForm(props.serviceId, {
    headline: form.value.headline,
    description: form.value.description,
    experience_start_year: form.value.experience_start_year,
    specialities: form.value.specialities,
    style_ids: form.value.style_ids,
    places: placesPayload(places.value),
    offers: offersPayload(offers.value),
    turnaround_days: form.value.turnaround_days,
    revisions_included: form.value.revisions_included,
    formats: form.value.formats,
    gear: parseGear(gearText.value)
  })
}

function showError(e) {
  toast.add({ severity: 'error', summary: e.message, life: 6000 })
}

async function uploadAudio(side, event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  busySide.value = side
  try {
    const stored = await soundEngineerApi.uploadAudio(props.serviceId, side, {
      file,
      ...uploads[side]
    })
    form.value.before_after = [
      ...form.value.before_after.filter((audio) => audio.side !== side),
      stored
    ]
    uploads[side] = { title: '', credit: '' }
    markChanged()
  } catch (e) {
    showError(e)
  } finally {
    busySide.value = null
  }
}

async function removeAudio(side) {
  busySide.value = side
  try {
    await soundEngineerApi.deleteAudio(props.serviceId, side)
    form.value.before_after = form.value.before_after.filter((audio) => audio.side !== side)
    markChanged()
  } catch (e) {
    showError(e)
  } finally {
    busySide.value = null
  }
}
</script>
