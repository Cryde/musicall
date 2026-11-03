<template>
  <section>
    <div class="flex justify-between items-center gap-3 mb-4 sm:mb-6">
      <h2 class="m-0 text-[1.375rem] sm:text-2xl lg:text-3xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('home.publications.title') }}
      </h2>
      <div class="flex shrink-0 items-center gap-2">
        <!-- In a wrapper, because PrimeVue's own display rule wins over `hidden` set on the Button itself. -->
        <span class="hidden md:inline-flex">
          <Button
            v-tooltip.bottom="t('home.publications.post_discovery_tooltip')"
            :label="t('home.publications.post_discovery')"
            icon="pi pi-plus"
            severity="secondary"
            outlined
            size="small"
            @click="$emit('open-discover-modal')"
          />
        </span>
        <!-- A plain link on a phone, as the announces' « Tout voir »: the button's padding pushed the title onto two lines. -->
        <router-link
          :to="{ name: 'app_publications' }"
          :aria-label="t('home.publications.see_more_aria_label')"
          class="sm:hidden inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
        >
          {{ t('home.publications.see_more') }}
          <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
        </router-link>
        <span class="hidden sm:inline-flex">
          <Button
            as="router-link"
            :to="{ name: 'app_publications' }"
            :aria-label="t('home.publications.see_more_aria_label')"
            :label="t('home.publications.see_more')"
            icon="pi pi-arrow-right"
            icon-pos="right"
            severity="secondary"
            text
            size="small"
          />
        </span>
      </div>
    </div>

    <!-- A phone gets three compact lines: four cover cards were a screen and a half there. -->
    <div
      v-if="isPhone"
      class="overflow-hidden rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
      :aria-busy="isLoading"
    >
      <div v-if="isLoading" class="divide-y divide-surface-200 dark:divide-surface-700">
        <div v-for="i in PHONE_PUBLICATIONS" :key="i" class="flex items-center gap-3.5 px-4 py-3">
          <div class="h-14 w-24 shrink-0 rounded-lg bg-surface-100 dark:bg-surface-800 animate-pulse" />
          <div class="h-9 flex-1 rounded-md bg-surface-100 dark:bg-surface-800 animate-pulse" />
        </div>
      </div>
      <ul v-else-if="publications.length > 0" class="m-0 p-0 list-none divide-y divide-surface-200 dark:divide-surface-700">
        <li v-for="publication in phonePublications" :key="publication.id">
          <PublicationRow
            :to="publicationRoute(publication)"
            :cover="publication.cover"
            :title="publication.title"
            :author="publication.author"
            class="px-4 py-3 hover:bg-surface-50 dark:hover:bg-surface-800/60"
          />
        </li>
      </ul>
      <p v-else class="m-0 px-4 py-8 text-center text-sm text-surface-600 dark:text-surface-300">
        {{ t('home.publications.empty') }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <template v-if="isLoading">
        <PublicationListItemSkeleton v-for="i in 4" :key="i" />
      </template>
      <FadeList v-else-if="publications.length > 0">
        <PublicationListItem
          v-for="publication in publications"
          :key="publication.id"
          :to-route="publicationRoute(publication)"
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
        {{ t('home.publications.empty') }}
      </div>
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useIsPhone } from '../../composables/useIsPhone.js'
import PublicationListItem from '../../views/Publication/PublicationListItem.vue'
import FadeList from '../Global/FadeList.vue'
import PublicationRow from '../Publication/PublicationRow.vue'
import PublicationListItemSkeleton from '../Skeleton/PublicationListItemSkeleton.vue'

const props = defineProps({
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

const { t } = useI18n()
const isPhone = useIsPhone()

const PHONE_PUBLICATIONS = 3
const phonePublications = computed(() => props.publications.slice(0, PHONE_PUBLICATIONS))

/** A course opens in the course reader, any other publication in the publication page. */
function publicationRoute(publication) {
  return {
    name: publication.sub_category.is_course ? 'app_course_show' : 'app_publication_show',
    params: { slug: publication.slug }
  }
}
</script>
