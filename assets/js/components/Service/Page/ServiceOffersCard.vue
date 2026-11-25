<template>
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-1 flex items-center gap-2">
      <i class="pi pi-wallet text-surface-400" aria-hidden="true" />{{ t('profile.service_page.offers') }}
    </h2>
    <ul class="divide-y divide-dashed divide-surface-200 dark:divide-surface-700">
      <li v-for="offer in offers" :key="offer.id" class="flex justify-between gap-4 py-3.5">
        <div class="min-w-0">
          <p class="text-surface-900 dark:text-surface-0">{{ offer.title }}</p>
          <p v-if="offer.description" class="text-sm text-surface-500 dark:text-surface-400">{{ offer.description }}</p>
        </div>
        <p class="shrink-0 text-right">
          <span class="block font-bold text-surface-900 dark:text-surface-0">{{ formatLessonPrice(offer.price) }}</span>
          <span class="block text-xs text-surface-500 dark:text-surface-400">{{ t(unitKeys[offer.unit]) }}</span>
        </p>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { formatLessonPrice } from '../../../utils/teacherPage.js'

/** « Formules »: a service's offers, the cheapest first as the API gives them (#1410). */
defineProps({
  offers: { type: Array, required: true },
  /** Each unit's words after a price, « le titre », as the kind names them. */
  unitKeys: { type: Object, required: true }
})

const { t } = useI18n()
</script>
