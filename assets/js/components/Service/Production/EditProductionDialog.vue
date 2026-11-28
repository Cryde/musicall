<template>
  <ServiceFormDialog
    v-model:visible="isVisible"
    :header="t('profile.producer.form.title')"
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
        :icon="SERVICE_KINDS.production.icon"
        id-prefix="prod"
        :specialities="specialityOptions"
        :headline-placeholder="t('profile.producer.form.headline_placeholder')"
        :description-placeholder="t('profile.producer.form.description_placeholder')"
        :start-year-label="t('profile.producer.form.start_year')"
        :disabled="isSaving"
      />

      <ServiceWorksEditor v-model:works="form.works" :service-id="serviceId" @changed="markChanged" />

      <ServicePlacesEditor v-model:places="places" id-prefix="prod" :disabled="isSaving">
        <label class="inline-flex items-center gap-2 p-3 bg-surface-50 dark:bg-surface-800 rounded-lg font-medium text-surface-900 dark:text-surface-0">
          <Checkbox v-model="places.remote" binary :disabled="isSaving" />
          {{ t('profile.producer.form.place_remote') }}
        </label>
      </ServicePlacesEditor>

      <ServiceOffersEditor v-model:offers="offers" :unit-options="unitOptions" :disabled="isSaving" />

      <ServiceMediaEditor v-model:media="form.media" :service-id="serviceId" :url-label="t('profile.producer.form.media_url')" @changed="markChanged" />
    </template>
  </ServiceFormDialog>
</template>

<script setup>
import Checkbox from 'primevue/checkbox'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import producerApi from '../../../api/service/producer.js'
import { useServiceForm } from '../../../composables/useServiceForm.js'
import { PRODUCTION_SPECIALITY_KEYS } from '../../../constants/production.js'
import { SERVICE_KINDS } from '../../../constants/serviceKinds.js'
import { FORM_UNIT_KEYS } from '../../../constants/soundEngineering.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { offerRow, offersPayload } from '../../../utils/serviceOffers.js'
import { placesForm, placesPayload } from '../../../utils/servicePlaces.js'
import ServiceBasicsEditor from '../Form/ServiceBasicsEditor.vue'
import ServiceFormDialog from '../Form/ServiceFormDialog.vue'
import ServiceMediaEditor from '../Form/ServiceMediaEditor.vue'
import ServiceOffersEditor from '../Form/ServiceOffersEditor.vue'
import ServicePlacesEditor from '../Form/ServicePlacesEditor.vue'
import ServiceWorksEditor from '../Works/ServiceWorksEditor.vue'

/**
 * A producer's form (#1413), from « Mes services » or their page: the service and its styles, « Réalisations », where
 * (remote work ticked until they say otherwise), the offers and the media.
 */
const props = defineProps({
  serviceId: { type: String, required: true }
})
/** `saved` once the form is saved; `changed` when a work or a media is written, each sent at once. */
const emit = defineEmits(['saved', 'changed'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const styleStore = useStyleStore()

const form = ref(null)
const places = ref(placesForm([]))
const offers = ref([])

const { isLoading, isSaving, error, handleShow, handleSave, markChanged, handleHide } =
  useServiceForm({ load, save, emit, visible: isVisible })

const specialityOptions = computed(() =>
  Object.entries(PRODUCTION_SPECIALITY_KEYS).map(([value, key]) => ({ value, label: t(key) }))
)
// Priced as a sound engineer's work is (ServiceKind::offerUnits()).
const unitOptions = computed(() =>
  Object.entries(FORM_UNIT_KEYS).map(([value, key]) => ({ value, label: t(key) }))
)

async function load() {
  const [data] = await Promise.all([
    producerApi.getForm(props.serviceId),
    styleStore.styles?.length ? null : styleStore.loadStyles()
  ])
  form.value = data
  places.value = placesForm(data.places, { remoteByDefault: true })
  offers.value = data.offers.map(offerRow)
}

function save() {
  return producerApi.saveForm(props.serviceId, {
    headline: form.value.headline,
    description: form.value.description,
    experience_start_year: form.value.experience_start_year,
    specialities: form.value.specialities,
    style_ids: form.value.style_ids,
    places: placesPayload(places.value),
    offers: offersPayload(offers.value)
  })
}
</script>
