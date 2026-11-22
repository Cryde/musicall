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
        <ServiceWorkCaption :work="work" :play-label="playLabel || t('profile.service_works.play')" @play="emit('play', $event)">
          <template v-if="work.images.length > 1" #default>
            <span class="flex gap-1.5">
              <Image
                v-for="(image, index) in work.images.slice(1)"
                :key="image.id"
                :src="image.url"
                :alt="t('profile.service_works.image_alt', { number: index + 2, title: work.title })"
                preview
                :pt="{ image: { class: 'size-8 rounded object-cover', loading: 'lazy' } }"
              />
            </span>
          </template>
        </ServiceWorkCaption>
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
import { workRows } from '../../../utils/serviceWorks.js'
import ReportButton from '../../Report/ReportButton.vue'
import ServiceWorkCaption from './ServiceWorkCaption.vue'

/**
 * « Réalisations » as credits (#1407): who the work was for and when, with its embed when there is one. The kinds
 * whose works are records or films show them this way; galleries are ServiceWorksGallery, sites come with #1412.
 */
const props = defineProps({
  title: { type: String, required: true },
  works: { type: Array, required: true },
  /** Whose service: the report names them, and their own works are not reported. */
  username: { type: String, required: true },
  reportable: { type: Boolean, default: false },
  /** Stands in for a work without an image. */
  icon: { type: String, default: 'pi pi-star' },
  /** What plays an embed: « Écouter » unless the kind's works are films (#1411). */
  playLabel: { type: String, default: null }
})
/** `play` with a work's embed, which the page plays in its own player. */
const emit = defineEmits(['play'])

const { t } = useI18n()

const rows = computed(() => workRows(props.works))
</script>
