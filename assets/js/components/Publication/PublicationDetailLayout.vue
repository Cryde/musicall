<template>
  <div class="flex justify-end mb-4">
    <Breadcrumb :items="breadcrumbItems" />
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-8">
    <article class="min-w-0">
      <header class="flex flex-col gap-3 mb-6">
        <router-link
          :to="{ name: categoryRouteName, params: { slug: publication.category.slug } }"
          class="inline-flex w-fit text-xs font-bold tracking-wider uppercase text-primary hover:opacity-75 transition-opacity"
        >
          {{ publication.category.title }}
        </router-link>

        <h1 class="text-3xl font-semibold leading-tight text-surface-900 dark:text-surface-0">
          {{ publication.title }}
        </h1>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-surface-600 dark:text-surface-300">
          <span>
            Par
            <router-link
              v-if="!publication.author.deletion_datetime"
              :to="{ name: 'app_user_public_profile', params: { username: publication.author.username } }"
              class="font-semibold text-surface-700 dark:text-surface-200 hover:text-primary transition-colors"
            >{{ authorName }}</router-link>
            <span v-else class="font-semibold text-surface-600 dark:text-surface-300">{{ authorName }}</span>
          </span>
          <span class="flex items-center gap-1">
            <i class="pi pi-calendar text-xs" />
            {{ relativeDate(publication.publication_datetime) }}
          </span>
          <span v-if="publication.reading_time > 0" class="flex items-center gap-1">
            <i class="pi pi-book text-xs" />
            {{ publication.reading_time }} min de lecture
          </span>
          <span class="flex items-center gap-1">
            <i class="pi pi-eye text-xs" />
            {{ publication.view_count }} vues
          </span>
          <div class="ml-auto">
            <VoteButtons :slug="publication.slug" />
          </div>
        </div>
      </header>

      <template v-if="publication.type.label === 'text'">
        <div
          class="box content is-shadowless publication-container p-4 bg-surface-0 dark:bg-surface-800 rounded-md"
          v-html="publication.content"
        />
      </template>

      <template v-if="publication.type.label === 'video'">
        <figure class="w-full">
          <iframe
            class="has-ratio aspect-video w-full border-0"
            :src="`https://www.youtube.com/embed/${publication.content}?showinfo=0`"
            :title="publication.title"
            allowfullscreen
          />
        </figure>
      </template>

      <div
        v-if="publication.tags?.length"
        class="mt-6 pt-6 border-t border-surface-200 dark:border-surface-700 flex flex-wrap gap-2"
      >
        <router-link
          v-for="tag in publication.tags"
          :key="tag.slug"
          :to="{ name: 'app_publication_tag', params: { slug: tag.slug } }"
          class="inline-flex transition-opacity hover:opacity-75"
          :aria-label="`Voir les publications avec le tag ${tag.label}`"
        >
          <Tag :value="`#${tag.label}`" severity="secondary" />
        </router-link>
      </div>

      <div class="mt-6">
        <PublicationShareBar :url="shareUrl" :title="shareTitle" />
      </div>

      <section
        v-if="relatedPublications.length > 0"
        class="mt-10"
      >
        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0 mb-4">
          {{ relatedSectionTitle }}
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <router-link
            v-for="related in relatedPublications"
            :key="related.slug"
            :to="{ name: related.sub_category.is_course ? 'app_course_show' : 'app_publication_show', params: { slug: related.slug } }"
            class="group flex flex-col bg-surface-0 dark:bg-surface-800 rounded-lg overflow-hidden border border-surface-200 dark:border-surface-700 hover:border-primary transition-colors"
          >
            <div class="aspect-video bg-surface-100 dark:bg-surface-900 overflow-hidden">
              <img
                v-if="related.cover"
                :src="related.cover"
                :alt="related.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="lazy"
              />
              <div v-else class="w-full h-full flex items-center justify-center text-surface-400">
                <i class="pi pi-image text-3xl" />
              </div>
            </div>
            <div class="p-3 flex flex-col gap-2">
              <span class="text-xs font-bold tracking-wider uppercase text-primary">
                {{ related.sub_category.title }}
              </span>
              <h3 class="text-sm font-semibold leading-snug text-surface-900 dark:text-surface-0 line-clamp-2 group-hover:text-primary transition-colors">
                {{ related.title }}
              </h3>
            </div>
          </router-link>
        </div>
      </section>

      <CommentThread
        v-if="publication.thread?.id"
        :thread-id="publication.thread.id"
      />
    </article>

    <aside class="flex flex-col gap-6 min-w-0">
      <LatestPublicationsWidget
        :exclude-id="publication.id ?? null"
        :count="6"
        :sub-category-type="latestSubCategoryType"
        :title="latestTitle"
        :icon="latestIcon"
        :empty-message="latestEmptyMessage"
      />
      <PopularTagsWidget :count="8" />
    </aside>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag'
import { computed } from 'vue'
import relativeDate from '../../helper/date/relative-date.js'
import { displayName } from '../../helper/user/displayName.js'
import Breadcrumb from '../../views/Global/Breadcrumb.vue'
import CommentThread from '../Comment/CommentThread.vue'
import PublicationShareBar from './PublicationShareBar.vue'
import LatestPublicationsWidget from './Sidebar/LatestPublicationsWidget.vue'
import PopularTagsWidget from './Sidebar/PopularTagsWidget.vue'
import VoteButtons from './VoteButtons.vue'

const props = defineProps({
  publication: { type: Object, required: true },
  relatedPublications: { type: Array, default: () => [] },
  breadcrumbItems: { type: Array, required: true },
  categoryRouteName: { type: String, required: true },
  relatedSectionTitle: { type: String, required: true },
  shareUrl: { type: String, required: true },
  shareTitle: { type: String, required: true },
  latestTitle: { type: String, default: 'Dernières publications' },
  latestIcon: { type: String, default: 'pi-clock' },
  latestSubCategoryType: { type: Number, default: null },
  latestEmptyMessage: { type: String, default: 'Aucune publication récente.' }
})

const authorName = computed(() => displayName(props.publication.author))
</script>
