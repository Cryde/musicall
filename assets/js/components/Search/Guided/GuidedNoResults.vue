<template>
  <div class="flex flex-col gap-4 mt-6">
    <section
      class="flex flex-col gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5"
      aria-labelledby="guided-empty-title"
    >
      <h2 id="guided-empty-title" class="m-0 text-xl font-semibold text-surface-900 dark:text-surface-0">Pas encore de {{ sought }} {{ where }}</h2>
      <template v-if="widening && (widening.wider_radius || widening.all_styles)">
        <p class="m-0 text-surface-700 dark:text-surface-300">En élargissant un peu, on en trouve :</p>
        <div class="flex flex-wrap gap-2">
          <Button
            v-if="widening.wider_radius"
            :label="`Jusqu'à ${widening.wider_radius_km} km`"
            severity="secondary"
            outlined
            @click="$emit('widen-radius', widening.wider_radius_km)"
          />
          <Button v-if="widening.all_styles" label="Tous styles" severity="secondary" outlined @click="$emit('all-styles')" />
        </div>
      </template>
    </section>

    <section
      class="flex flex-col gap-3 rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5"
      aria-labelledby="guided-publish-title"
    >
      <h2 id="guided-publish-title" class="m-0 text-lg font-semibold text-surface-900 dark:text-surface-0">
        Laissez les {{ sought }} venir à vous
      </h2>
      <p class="m-0 text-surface-700 dark:text-surface-300">
        Publiez votre annonce avec ces critères : elle apparaîtra dans leurs recherches.
      </p>
      <Button label="Publier mon annonce" icon="pi pi-megaphone" severity="success" class="self-start" @click="$emit('publish')" />
    </section>
  </div>
</template>

<script setup>
import Button from 'primevue/button'

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
</script>
