<template>
  <section class="flex flex-col gap-6" aria-labelledby="home-announces-title">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
      <div class="flex flex-col gap-4">
        <h2 id="home-announces-title" class="m-0 text-2xl lg:text-3xl font-bold text-surface-900 dark:text-surface-0">
          Dernières annonces
        </h2>
        <SelectButton
          :model-value="filter"
          :options="FILTER_OPTIONS"
          option-label="label"
          option-value="value"
          :allow-empty="false"
          aria-label="Filtrer les annonces"
          class="max-w-full"
          @update:model-value="$emit('update:filter', $event)"
        >
          <template #option="{ option }">
            <span class="sm:hidden">{{ option.short }}</span>
            <span class="hidden sm:inline">{{ option.label }}</span>
          </template>
        </SelectButton>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <Button label="Poster une annonce" icon="pi pi-plus" severity="info" @click="$emit('open-announce-modal')" />
        <router-link
          :to="{ name: 'app_search_musician' }"
          class="inline-flex items-center gap-2 font-semibold text-primary hover:underline"
        >
          Voir toutes les annonces
          <i class="pi pi-arrow-right text-sm" aria-hidden="true" />
        </router-link>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-5" :aria-busy="isLoading">
      <template v-if="isLoading">
        <AnnounceCardSkeleton v-for="i in 6" :key="i" />
      </template>
      <FadeList v-else-if="announces.length > 0">
        <HomeAnnounceCard
          v-for="announce in announces"
          :key="announce.id"
          :announce="announce"
          class="transition-all duration-300 hover:shadow-lg hover:-translate-y-1"
          @contact="$emit('contact-announce', $event)"
        />
      </FadeList>
      <p v-else class="col-span-full m-0 text-center py-12 text-surface-600 dark:text-surface-300">
        Aucune annonce pour le moment.
      </p>
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../../constants/types.js'
import { ANNOUNCE_FILTER_ALL } from '../../utils/homeSearch.js'
import FadeList from '../Global/FadeList.vue'
import AnnounceCardSkeleton from '../Skeleton/AnnounceCardSkeleton.vue'
import HomeAnnounceCard from './HomeAnnounceCard.vue'

// The filter is who posted the announce, which is the announce type. A phone gets the short labels.
const FILTER_OPTIONS = [
  { label: 'Toutes', short: 'Toutes', value: ANNOUNCE_FILTER_ALL },
  { label: 'Musiciens qui cherchent un groupe', short: 'Musiciens', value: TYPES_ANNOUNCE_BAND },
  { label: 'Groupes qui cherchent un musicien', short: 'Groupes', value: TYPES_ANNOUNCE_MUSICIAN }
]

defineProps({
  announces: { type: Array, required: true },
  isLoading: { type: Boolean, default: true },
  /** ANNOUNCE_FILTER_ALL, or the announce type to narrow to. */
  filter: { type: [String, Number], default: ANNOUNCE_FILTER_ALL }
})

defineEmits(['open-announce-modal', 'contact-announce', 'update:filter'])
</script>
