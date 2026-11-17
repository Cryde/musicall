<template>
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-1 flex items-center gap-2">
      <i class="pi pi-star text-surface-400" aria-hidden="true" />{{ title }}
    </h2>
    <ul class="divide-y divide-dashed divide-surface-200 dark:divide-surface-700">
      <li v-for="work in rows" :key="work.id" class="flex items-start gap-4 py-3.5">
        <Image
          v-if="work.images.length"
          :src="work.images[0].url"
          :alt="t('profile.service_works.image_alt', { number: 1, title: work.title })"
          preview
          class="shrink-0"
          :pt="{ image: { class: 'size-16 rounded-lg object-cover', loading: 'lazy' } }"
        />
        <span v-else class="size-16 shrink-0 rounded-lg bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
          <i :class="icon" class="text-xl text-surface-400" aria-hidden="true" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-surface-900 dark:text-surface-0">{{ work.title }}</p>
          <p v-if="work.byline" class="text-sm text-surface-600 dark:text-surface-400">{{ work.byline }}</p>
          <div v-if="work.media || work.domain || work.images.length > 1" class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2 text-sm">
            <button
              v-if="work.media"
              type="button"
              class="inline-flex items-center gap-1.5 font-medium text-primary-600 dark:text-primary-400 hover:underline"
              @click="emit('play', work.media)"
            >
              <i class="pi pi-play-circle" aria-hidden="true" />{{ t('profile.service_works.play') }}
            </button>
            <a
              v-if="work.domain"
              :href="work.link_url"
              target="_blank"
              rel="nofollow ugc noopener"
              class="inline-flex items-center gap-1.5 text-surface-600 dark:text-surface-300 hover:underline"
            >
              <i class="pi pi-external-link text-xs" aria-hidden="true" />{{ work.domain }}
            </a>
            <span v-if="work.images.length > 1" class="flex gap-1.5">
              <Image
                v-for="(image, index) in work.images.slice(1)"
                :key="image.id"
                :src="image.url"
                :alt="t('profile.service_works.image_alt', { number: index + 2, title: work.title })"
                preview
                :pt="{ image: { class: 'size-8 rounded object-cover', loading: 'lazy' } }"
              />
            </span>
          </div>
        </div>
        <ReportButton
          v-if="reportable"
          target-type="service_work"
          :target-id="work.id"
          :author-username="username"
          :aria-label="t('profile.service_works.report', { title: work.title })"
          icon-only
        />
      </li>
    </ul>
  </section>
</template>

<script setup>
import Image from 'primevue/image'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { workByline, workLinkDomain } from '../../../utils/serviceWorks.js'
import ReportButton from '../../Report/ReportButton.vue'

/**
 * « Réalisations » as credits (#1407): who the work was for and when, with its embed when there is one. The kinds
 * whose works are records or films show them this way; galleries and sites come with the kinds that need them.
 */
const props = defineProps({
  title: { type: String, required: true },
  works: { type: Array, required: true },
  /** Whose service: the report names them, and their own works are not reported. */
  username: { type: String, required: true },
  reportable: { type: Boolean, default: false },
  /** Stands in for a work without an image. */
  icon: { type: String, default: 'pi pi-star' }
})
/** `play` with a work's embed, which the page plays in its own player. */
const emit = defineEmits(['play'])

const { t } = useI18n()

const rows = computed(() =>
  props.works.map((work) => ({
    ...work,
    byline: workByline(work),
    domain: workLinkDomain(work.link_url)
  }))
)
</script>
