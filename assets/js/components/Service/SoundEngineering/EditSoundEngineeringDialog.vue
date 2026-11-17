<template>
  <Dialog
    v-model:visible="isVisible"
    modal
    :header="t('profile.sound_engineer.form.title')"
    :style="{ width: '60rem' }"
    :breakpoints="{ '1280px': '90vw', '768px': '95vw' }"
    :closable="!isSaving"
    :close-on-escape="!isSaving"
    @show="load"
    @hide="handleHide"
  >
    <div v-if="isLoading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-2xl" aria-hidden="true" />
    </div>

    <div v-else-if="form" class="flex flex-col gap-8">
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

      <!-- What the engineer offers -->
      <section class="flex flex-col gap-5">
        <h3 :class="SECTION_CLASS"><i class="pi pi-sliders-v text-primary-500" aria-hidden="true" />{{ t('profile.sound_engineer.form.section_service') }}</h3>
        <div class="flex flex-col gap-2">
          <label for="se-headline" :class="LABEL_CLASS">{{ t('profile.sound_engineer.form.headline') }}</label>
          <InputText id="se-headline" v-model="form.headline" :maxlength="150" :placeholder="t('profile.sound_engineer.form.headline_placeholder')" :disabled="isSaving" />
        </div>
        <fieldset class="flex flex-col gap-2">
          <legend :class="[LABEL_CLASS, 'mb-2']">{{ t('profile.sound_engineer.form.specialities') }}</legend>
          <div class="flex flex-wrap gap-x-5 gap-y-2">
            <label v-for="speciality in SPECIALITIES" :key="speciality" class="inline-flex items-center gap-2">
              <Checkbox v-model="form.specialities" :value="speciality" :disabled="isSaving" />
              {{ t(SPECIALITY_KEYS[speciality]) }}
            </label>
          </div>
        </fieldset>
        <div class="flex flex-col gap-2">
          <label for="se-description" :class="LABEL_CLASS">{{ t('profile.sound_engineer.form.description') }}</label>
          <Textarea id="se-description" v-model="form.description" rows="4" :maxlength="5000" :placeholder="t('profile.sound_engineer.form.description_placeholder')" :disabled="isSaving" />
        </div>
        <div class="grid gap-5 md:grid-cols-2">
          <div class="flex flex-col gap-2">
            <label for="se-years" :class="LABEL_CLASS">{{ t('profile.sound_engineer.form.years') }}</label>
            <InputNumber fluid v-model="form.years_of_experience" input-id="se-years" :min="0" :max="70" :disabled="isSaving" />
          </div>
          <div class="flex flex-col gap-2">
            <label for="se-styles" :class="LABEL_CLASS">{{ t('profile.sound_engineer.form.styles') }}</label>
            <MultiSelect
              v-model="form.style_ids"
              input-id="se-styles"
              :options="styleStore.styles || []"
              option-label="name"
              option-value="id"
              :selection-limit="10"
              display="chip"
              filter
              :placeholder="t('profile.teacher.edit.styles_placeholder')"
              :disabled="isSaving"
            />
          </div>
        </div>
      </section>

      <!-- Where -->
      <section class="flex flex-col gap-4">
        <h3 :class="SECTION_CLASS"><i class="pi pi-map-marker text-primary-500" aria-hidden="true" />{{ t('profile.sound_engineer.form.section_places') }}</h3>
        <div v-for="key in ['studio', 'client']" :key="key" class="flex flex-col gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
          <label class="inline-flex items-center gap-2 font-medium text-surface-900 dark:text-surface-0">
            <Checkbox v-model="places[key].enabled" binary :disabled="isSaving" />
            {{ t(FORM_PLACE_KEYS[key]) }}
          </label>
          <template v-if="places[key].enabled">
            <label :for="`se-city-${key}`" class="sr-only">{{ t('profile.sound_engineer.form.city') }}</label>
            <CityAutoComplete
              v-model="citySearch[key]"
              :input-id="`se-city-${key}`"
              :placeholder="t('profile.teacher.edit.city_placeholder')"
              :disabled="isSaving"
            />
            <InputText
              v-if="key === 'studio'"
              v-model="places.studio.address"
              :placeholder="t('profile.teacher.edit.address_placeholder')"
              :aria-label="t('profile.sound_engineer.form.address')"
              :disabled="isSaving"
            />
            <div v-else class="flex items-center gap-2">
              <InputNumber fluid v-model="places.client.radius" :min="1" :max="500" suffix=" km" :aria-label="t('profile.sound_engineer.form.radius')" :disabled="isSaving" class="w-36" />
              <span class="text-sm text-surface-500 dark:text-surface-400">{{ t('profile.teacher.edit.radius_suffix') }}</span>
            </div>
          </template>
        </div>
        <label class="inline-flex items-center gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg font-medium text-surface-900 dark:text-surface-0">
          <Checkbox v-model="places.remote" binary :disabled="isSaving" />
          {{ t('profile.sound_engineer.form.place_remote') }}
        </label>
      </section>

      <!-- « Formules » -->
      <section class="flex flex-col gap-3">
        <h3 :class="SECTION_CLASS"><i class="pi pi-wallet text-primary-500" aria-hidden="true" />{{ t('profile.sound_engineer.form.section_offers') }}</h3>
        <div v-for="(offer, index) in offers" :key="offer.key" class="grid gap-2 md:grid-cols-[10rem_minmax(0,1fr)_8rem_auto] items-start p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
          <Select v-model="offer.unit" :options="unitOptions" option-label="label" option-value="value" :aria-label="t('profile.sound_engineer.form.offer_unit')" :disabled="isSaving" />
          <div class="flex flex-col gap-2">
            <InputText v-model="offer.title" :maxlength="100" :placeholder="t('profile.sound_engineer.form.offer_title')" :aria-label="t('profile.sound_engineer.form.offer_title')" :disabled="isSaving" />
            <InputText v-model="offer.description" :maxlength="255" :placeholder="t('profile.sound_engineer.form.offer_description')" :aria-label="t('profile.sound_engineer.form.offer_description')" :disabled="isSaving" />
          </div>
          <InputNumber fluid v-model="offer.price" mode="currency" currency="EUR" :locale="locale" :min="0" :max="100000" :aria-label="t('profile.sound_engineer.form.offer_price')" :disabled="isSaving" />
          <Button icon="pi pi-times" severity="danger" text rounded :aria-label="t('profile.sound_engineer.form.remove_offer')" :disabled="isSaving" @click="offers.splice(index, 1)" />
        </div>
        <Button :label="t('profile.sound_engineer.form.add_offer')" icon="pi pi-plus" severity="secondary" size="small" class="w-fit" :disabled="isSaving || offers.length >= MAX_OFFERS" @click="addOffer" />
      </section>

      <!-- Turnaround, revisions, formats, gear -->
      <section class="flex flex-col gap-5">
        <h3 :class="SECTION_CLASS"><i class="pi pi-clock text-primary-500" aria-hidden="true" />{{ t('profile.sound_engineer.form.section_delivery') }}</h3>
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
      </section>

      <!-- « Avant, après », sent as soon as chosen -->
      <section class="flex flex-col gap-3">
        <h3 :class="SECTION_CLASS"><i class="pi pi-volume-up text-primary-500" aria-hidden="true" />{{ t('profile.sound_engineer_page.before_after') }}</h3>
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
      </section>

      <ServiceWorksEditor v-model:works="form.works" :service-id="serviceId" :section-class="SECTION_CLASS" @changed="markChanged" />

      <!-- Media, added and removed at once -->
      <section class="flex flex-col gap-3">
        <h3 :class="SECTION_CLASS"><i class="pi pi-video text-primary-500" aria-hidden="true" />{{ t('profile.sound_engineer_page.media') }}</h3>
        <ul v-if="form.media.length" class="flex flex-col gap-2">
          <li v-for="item in form.media" :key="item.id" class="flex items-center justify-between gap-3 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
            <span class="min-w-0 truncate text-surface-900 dark:text-surface-0">{{ item.title || item.url }}</span>
            <Button icon="pi pi-trash" severity="danger" text rounded :aria-label="t('profile.sound_engineer.form.remove_media', { title: item.title || item.url })" :disabled="isMediaBusy" @click="removeMedia(item)" />
          </li>
        </ul>
        <div class="flex flex-col sm:flex-row gap-2">
          <InputText v-model="mediaUrl" type="url" class="flex-1" :placeholder="t('profile.sound_engineer.form.media_url')" :aria-label="t('profile.sound_engineer.form.media_url')" :disabled="isMediaBusy" />
          <Button :label="t('profile.sound_engineer.form.add_media')" icon="pi pi-plus" severity="secondary" :loading="isMediaBusy" :disabled="!mediaUrl.trim()" @click="addMedia" />
        </div>
      </section>
    </div>

    <template #footer>
      <Button :label="t('profile.services.cancel')" severity="secondary" text :disabled="isSaving" @click="isVisible = false" />
      <Button :label="t('profile.sound_engineer.form.save')" icon="pi pi-check" :loading="isSaving" :disabled="!form" @click="save" />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { useToast } from 'primevue/usetoast'
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import soundEngineerApi from '../../../api/service/soundEngineer.js'
import {
  FORM_PLACE_KEYS,
  FORM_UNIT_KEYS,
  FORMAT_KEYS,
  SIDE_KEYS,
  SPECIALITY_KEYS
} from '../../../constants/soundEngineering.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { formatVoiceNoteDuration } from '../../../utils/chatVoiceNote.js'
import {
  DELIVERY_FORMATS,
  MAX_GEAR,
  OFFER_UNITS,
  offersPayload,
  parseGear,
  placesForm,
  placesPayload,
  SPECIALITIES
} from '../../../utils/soundEngineerPage.js'
import CityAutoComplete from '../../Global/CityAutoComplete.vue'
import ServiceWorksEditor from '../Works/ServiceWorksEditor.vue'

/** A sound engineer's form (#1410), from « Mes services » or their page. */
const props = defineProps({
  serviceId: { type: String, required: true }
})
/** `saved` once the form is saved; `changed` when a sound, a media or a work is written, each sent at once. */
const emit = defineEmits(['saved', 'changed'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const SECTION_CLASS =
  'flex items-center gap-2 pb-2 border-b border-surface-200 dark:border-surface-700 text-base font-semibold text-surface-900 dark:text-surface-0'
const LABEL_CLASS = 'font-medium text-surface-900 dark:text-surface-0'
const SIDES = Object.keys(SIDE_KEYS)
/** Mirrors MySoundEngineering::MAX_OFFERS. */
const MAX_OFFERS = 12
/** Mirrors ServiceAudioConverter: what libmagic reads, the browser only filters the picker with it. */
const AUDIO_ACCEPT =
  '.wav,.aif,.aiff,.flac,.mp3,.m4a,audio/wav,audio/x-wav,audio/aiff,audio/x-aiff,audio/flac,audio/mpeg,audio/mp4,audio/x-m4a'

const { t, locale } = useI18n()
const toast = useToast()
const styleStore = useStyleStore()

const isLoading = ref(false)
const isSaving = ref(false)
const isMediaBusy = ref(false)
const busySide = ref(null)
const error = ref('')
const form = ref(null)
const places = ref(placesForm([]))
const offers = ref([])
const gearText = ref('')
const mediaUrl = ref('')
/** The city fields' own values: the typed text until a city is picked, then `{ name, context, latitude, longitude }`. */
const citySearch = reactive({ studio: null, client: null })
/** A sound, a media or a work sent while the dialog was open: the page refreshes once, as it closes. */
let hasChanges = false
const uploads = reactive({ before: { title: '', credit: '' }, after: { title: '', credit: '' } })
let offerKey = 0

const unitOptions = computed(() =>
  OFFER_UNITS.map((unit) => ({ value: unit, label: t(FORM_UNIT_KEYS[unit]) }))
)

function audioOf(side) {
  return form.value.before_after.find((audio) => audio.side === side) ?? null
}

function offerRow(offer = { unit: 'track', title: '', description: '', price: null }) {
  offerKey += 1
  return {
    key: offerKey,
    unit: offer.unit,
    title: offer.title ?? '',
    description: offer.description ?? '',
    price: offer.price === null ? null : offer.price / 100
  }
}

function addOffer() {
  offers.value.push(offerRow())
}

function cityValue(place) {
  return place.city
    ? {
        name: place.city,
        context: place.country,
        latitude: place.latitude,
        longitude: place.longitude
      }
    : null
}

// A city counts once picked; emptying the field clears it.
watch(citySearch, () => {
  for (const key of ['studio', 'client']) {
    const city = citySearch[key]
    if (city && typeof city === 'object') {
      Object.assign(places.value[key], {
        city: city.name,
        country: city.context?.split(', ').pop() || '',
        latitude: city.latitude,
        longitude: city.longitude
      })
    } else if (!city) {
      Object.assign(places.value[key], { city: '', country: '', latitude: null, longitude: null })
    }
  }
})

async function load() {
  isLoading.value = true
  error.value = ''
  try {
    const [data] = await Promise.all([
      soundEngineerApi.getForm(props.serviceId),
      styleStore.styles?.length ? null : styleStore.loadStyles()
    ])
    form.value = data
    places.value = placesForm(data.places)
    citySearch.studio = cityValue(places.value.studio)
    citySearch.client = cityValue(places.value.client)
    offers.value = data.offers.map(offerRow)
    gearText.value = data.gear.join('\n')
  } catch (e) {
    error.value = e.message
  } finally {
    isLoading.value = false
  }
}

async function save() {
  isSaving.value = true
  error.value = ''
  try {
    await soundEngineerApi.saveForm(props.serviceId, {
      headline: form.value.headline,
      description: form.value.description,
      years_of_experience: form.value.years_of_experience,
      specialities: form.value.specialities,
      style_ids: form.value.style_ids,
      places: placesPayload(places.value),
      offers: offersPayload(offers.value),
      turnaround_days: form.value.turnaround_days,
      revisions_included: form.value.revisions_included,
      formats: form.value.formats,
      gear: parseGear(gearText.value)
    })
    hasChanges = false
    emit('saved')
    isVisible.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    isSaving.value = false
  }
}

function markChanged() {
  hasChanges = true
}

function handleHide() {
  if (hasChanges) emit('changed')
  hasChanges = false
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
    hasChanges = true
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
    hasChanges = true
  } catch (e) {
    showError(e)
  } finally {
    busySide.value = null
  }
}

async function addMedia() {
  isMediaBusy.value = true
  try {
    const media = await soundEngineerApi.addMedia(props.serviceId, { url: mediaUrl.value.trim() })
    form.value.media = [...form.value.media, media]
    mediaUrl.value = ''
    hasChanges = true
  } catch (e) {
    showError(e)
  } finally {
    isMediaBusy.value = false
  }
}

async function removeMedia(item) {
  isMediaBusy.value = true
  try {
    await soundEngineerApi.deleteMedia(props.serviceId, item.id)
    form.value.media = form.value.media.filter((media) => media.id !== item.id)
    hasChanges = true
  } catch (e) {
    showError(e)
  } finally {
    isMediaBusy.value = false
  }
}
</script>
