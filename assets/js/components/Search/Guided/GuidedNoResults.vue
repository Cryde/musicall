<template>
  <div class="flex flex-col gap-4 mt-6">
    <section
      class="flex flex-col gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5"
      aria-labelledby="guided-empty-title"
    >
      <h2 id="guided-empty-title" class="m-0 text-xl font-semibold text-surface-900 dark:text-surface-0">{{ t('search.guided.no_results_title', { sought, where }) }}</h2>
      <template v-if="widening && (widening.wider_radius || widening.all_styles)">
        <p class="m-0 text-surface-700 dark:text-surface-300">{{ t('search.guided.widening_intro') }}</p>
        <div class="flex flex-wrap gap-2">
          <Button
            v-if="widening.wider_radius"
            :label="t('search.guided.wider_radius', { km: widening.wider_radius_km })"
            severity="secondary"
            outlined
            @click="$emit('widen-radius', widening.wider_radius_km)"
          />
          <Button v-if="widening.all_styles" :label="t('search.guided.all_styles')" severity="secondary" outlined @click="$emit('all-styles')" />
        </div>
      </template>
    </section>

    <section
      class="flex flex-col gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5"
      aria-labelledby="guided-publish-title"
    >
      <h2 id="guided-publish-title" class="m-0 text-lg font-semibold text-surface-900 dark:text-surface-0">
        {{ t('search.guided.publish_title', { sought }) }}
      </h2>
      <p class="m-0 text-surface-700 dark:text-surface-300">
        {{ t('search.guided.publish_text') }}
      </p>
      <Button :label="t('search.guided.publish')" icon="pi pi-megaphone" severity="success" class="self-start" @click="$emit('publish')" />
    </section>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'

/**
 * A guided search that found nothing (#1084): the wider searches that would find something, and the
 * member's own announce. Which widening helps is asked to the server, which never says how many.
 */
defineProps({
  sought: { type: String, required: true },
  /** « autour de Liège », or nothing. */
  where: { type: String, default: '' },
  widening: { type: Object, default: null }
})

defineEmits(['widen-radius', 'all-styles', 'publish'])

const { t } = useI18n()
</script>
