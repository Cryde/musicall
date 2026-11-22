<template>
  <ServiceFormDialog
    v-model:visible="isVisible"
    :header="t(config.text.formTitle)"
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
        :icon="SERVICE_KINDS[kind].icon"
        :id-prefix="idPrefix"
        :specialities="specialityOptions"
        :headline-placeholder="t(config.text.headlinePlaceholder)"
        :description-placeholder="t('profile.visual_service.form.description_placeholder')"
        :years-label="t('profile.visual_service.form.years')"
        :disabled="isSaving"
      />

      <ServiceWorksEditor
        v-model:works="form.works"
        :service-id="serviceId"
        :help-key="config.text.worksHelp"
        :media-label-key="VISUAL_WORKS_MEDIA_LABEL_KEY"
        @changed="markChanged"
      />

      <ServicePlacesEditor v-model:places="places" :id-prefix="idPrefix" :disabled="isSaving" />

      <ServiceOffersEditor v-model:offers="offers" :unit-options="unitOptions" :disabled="isSaving" />

      <!-- What is delivered, and when -->
      <ServiceFormSection icon="pi pi-send" :title="t('profile.visual_service.form.section_delivery')" class="gap-5">
        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_14rem]">
          <div class="flex flex-col gap-2">
            <label :for="`${idPrefix}-deliverable`" :class="LABEL_CLASS">{{ t('profile.visual_service.form.deliverable') }}</label>
            <InputText :id="`${idPrefix}-deliverable`" v-model="form.deliverable" :maxlength="MAX_DELIVERABLE_LENGTH" :placeholder="t(config.text.deliverablePlaceholder)" :disabled="isSaving" />
          </div>
          <div class="flex flex-col gap-2">
            <label :for="`${idPrefix}-delivery-days`" :class="LABEL_CLASS">{{ t('profile.visual_service.form.delivery_days') }}</label>
            <InputNumber fluid v-model="form.delivery_days" :input-id="`${idPrefix}-delivery-days`" :min="1" :max="365" :suffix="` ${t('profile.visual_service.form.days_suffix')}`" :disabled="isSaving" />
          </div>
        </div>
      </ServiceFormSection>

      <ServiceMediaEditor v-model:media="form.media" :service-id="serviceId" :url-label="t('profile.visual_service.form.media_url')" @changed="markChanged" />
    </template>
  </ServiceFormDialog>
</template>

<script setup>
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import visualServiceApi from '../../../api/service/visualService.js'
import { useServiceForm } from '../../../composables/useServiceForm.js'
import { SERVICE_KINDS } from '../../../constants/serviceKinds.js'
import {
  VISUAL_FORM_UNIT_KEYS,
  VISUAL_SERVICE_KINDS,
  VISUAL_WORKS_MEDIA_LABEL_KEY
} from '../../../constants/visualServices.js'
import { useStyleStore } from '../../../store/attribute/style.js'
import { offerRow, offersPayload } from '../../../utils/serviceOffers.js'
import { placesForm, placesPayload } from '../../../utils/servicePlaces.js'
import { kindSpecialities } from '../../../utils/visualServicePage.js'
import ServiceBasicsEditor from '../Form/ServiceBasicsEditor.vue'
import ServiceFormDialog from '../Form/ServiceFormDialog.vue'
import ServiceFormSection from '../Form/ServiceFormSection.vue'
import ServiceMediaEditor from '../Form/ServiceMediaEditor.vue'
import ServiceOffersEditor from '../Form/ServiceOffersEditor.vue'
import ServicePlacesEditor from '../Form/ServicePlacesEditor.vue'
import ServiceWorksEditor from '../Works/ServiceWorksEditor.vue'

/**
 * A photographer's or a videographer's form (#1411), from « Mes services » or their page: « Réalisations » first, then
 * where, the offers, what is delivered and when, and the media.
 */
const props = defineProps({
  /** `photography` or `video`. */
  kind: { type: String, required: true },
  serviceId: { type: String, required: true }
})
/** `saved` once the form is saved; `changed` when a work or a media is written, each sent at once. */
const emit = defineEmits(['saved', 'changed'])
const isVisible = defineModel('visible', { type: Boolean, default: false })

const LABEL_CLASS = 'font-medium text-surface-900 dark:text-surface-0'
/** Mirrors DeliveryDetails::MAX_DELIVERABLE_LENGTH. */
const MAX_DELIVERABLE_LENGTH = 100

const { t } = useI18n()
const styleStore = useStyleStore()

const form = ref(null)
const places = ref(placesForm([]))
const offers = ref([])

const { isLoading, isSaving, error, handleShow, handleSave, markChanged, handleHide } =
  useServiceForm({ load, save, emit, visible: isVisible })

const config = computed(() => VISUAL_SERVICE_KINDS[props.kind])
const idPrefix = computed(() => `visual-${props.kind}`)
const specialityOptions = computed(() =>
  kindSpecialities(props.kind).map((speciality) => ({
    value: speciality,
    label: t(config.value.specialityKeys[speciality])
  }))
)
const unitOptions = computed(() =>
  Object.entries(VISUAL_FORM_UNIT_KEYS).map(([unit, key]) => ({ value: unit, label: t(key) }))
)

async function load() {
  const [data] = await Promise.all([
    visualServiceApi.getForm(props.kind, props.serviceId),
    styleStore.styles?.length ? null : styleStore.loadStyles()
  ])
  form.value = data
  places.value = placesForm(data.places)
  offers.value = data.offers.map(offerRow)
}

function save() {
  return visualServiceApi.saveForm(props.kind, props.serviceId, {
    headline: form.value.headline,
    description: form.value.description,
    years_of_experience: form.value.years_of_experience,
    specialities: form.value.specialities,
    style_ids: form.value.style_ids,
    places: placesPayload(places.value),
    offers: offersPayload(offers.value),
    delivery_days: form.value.delivery_days,
    deliverable: form.value.deliverable
  })
}
</script>
