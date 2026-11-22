<template>
  <ServiceFormSection icon="pi pi-wallet" :title="t('profile.service_form.section_offers')" class="gap-3">
    <div v-for="(offer, index) in offers" :key="offer.key" class="grid gap-2 md:grid-cols-[10rem_minmax(0,1fr)_8rem_auto] items-start p-3 bg-surface-50 dark:bg-surface-800 rounded-lg">
      <Select v-model="offer.unit" :options="unitOptions" option-label="label" option-value="value" :aria-label="t('profile.service_form.offer_unit')" :disabled="disabled" />
      <div class="flex flex-col gap-2">
        <InputText v-model="offer.title" :maxlength="100" :placeholder="t('profile.service_form.offer_title')" :aria-label="t('profile.service_form.offer_title')" :disabled="disabled" />
        <InputText v-model="offer.description" :maxlength="255" :placeholder="t('profile.service_form.offer_description')" :aria-label="t('profile.service_form.offer_description')" :disabled="disabled" />
      </div>
      <InputNumber fluid v-model="offer.price" mode="currency" currency="EUR" :locale="locale" :min="0" :max="100000" :aria-label="t('profile.service_form.offer_price')" :disabled="disabled" />
      <Button icon="pi pi-times" severity="danger" text rounded :aria-label="t('profile.service_form.remove_offer')" :disabled="disabled" @click="offers.splice(index, 1)" />
    </div>
    <Button :label="t('profile.service_form.add_offer')" icon="pi pi-plus" severity="secondary" size="small" class="w-fit" :disabled="disabled || offers.length >= MAX_OFFERS" @click="offers.push(offerRow({ unit: unitOptions[0].value }))" />
  </ServiceFormSection>
</template>

<script setup>
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useI18n } from 'vue-i18n'
import { offerRow } from '../../../utils/serviceOffers.js'
import ServiceFormSection from './ServiceFormSection.vue'

/** « Formules » in a service's form (#1410, #1411), priced in the units of its kind. */
defineProps({
  /** `{ value, label }`, the unit a new offer starts in first. */
  unitOptions: { type: Array, required: true },
  disabled: { type: Boolean, default: false }
})
/** Rows from offerRow(), sent through offersPayload(). */
const offers = defineModel('offers', { type: Array, required: true })

/** Mirrors the MAX_OFFERS of the kinds' resources. */
const MAX_OFFERS = 12

const { t, locale } = useI18n()
</script>
