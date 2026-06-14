<template>
  <section class="mb-12">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        Dernières publications
      </h2>
      <div class="flex gap-2">
        <Button
          v-tooltip.bottom="'Ajouter une vidéo YouTube découverte'"
          label="Poster une découverte"
          icon="pi pi-plus"
          severity="info"
          size="small"
          class="hidden md:inline-flex"
          @click="$emit('open-discover-modal')"
        />
        <router-link :to="{ name: 'app_publications' }" aria-label="Voir plus de publications">
          <Button label="Voir plus" icon="pi pi-arrow-right" iconPos="right" severity="secondary" text size="small" />
        </router-link>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <template v-if="isLoading">
        <PublicationListItemSkeleton v-for="i in 4" :key="i" />
      </template>
      <FadeList v-else-if="publications.length > 0">
        <PublicationListItem
          v-for="publication in publications"
          :key="publication.id"
          :to-route="{ name: publication.sub_category.is_course ? 'app_course_show' : 'app_publication_show', params: { slug: publication.slug } }"
          :cover="publication.cover"
          :title="publication.title"
          :description="publication.description"
          :category="publication.sub_category"
          :author="publication.author"
          :date="publication.publication_datetime"
          :slug="publication.slug"
          :upvotes="publication.upvotes ?? 0"
          :downvotes="publication.downvotes ?? 0"
          :user-vote="publication.user_vote ?? null"
        />
      </FadeList>
      <div v-else class="col-span-full text-center py-12 text-surface-500">
        Aucune publication pour le moment.
      </div>
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import PublicationListItem from '../../views/Publication/PublicationListItem.vue'
import FadeList from '../Global/FadeList.vue'
import PublicationListItemSkeleton from '../Skeleton/PublicationListItemSkeleton.vue'

defineProps({
  publications: {
    type: Array,
    required: true
  },
  isLoading: {
    type: Boolean,
    default: true
  }
})

defineEmits(['open-discover-modal'])
</script>
