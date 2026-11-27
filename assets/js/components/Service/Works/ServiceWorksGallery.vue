<template>
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-4 flex items-center gap-2">
      <i class="pi pi-images text-surface-400" aria-hidden="true" />{{ title }}
    </h2>
    <ul class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-5">
      <li v-for="work in rows" :key="work.id" class="flex flex-col min-w-0">
        <button
          v-if="work.images.length"
          type="button"
          class="group relative block aspect-[4/3] rounded-lg overflow-hidden bg-surface-100 dark:bg-surface-800 cursor-pointer"
          :aria-label="t('profile.service_works.open_gallery', { title: work.title, count: work.images.length }, work.images.length)"
          @click="openWork = work"
        >
          <img :src="work.images[0].url" alt="" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" />
          <span v-if="work.images.length > 1" class="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-md bg-black/60 px-2 py-0.5 text-xs font-semibold text-white" aria-hidden="true">
            <i class="pi pi-images text-[10px]" />{{ work.images.length }}
          </span>
        </button>
        <span v-else class="aspect-[4/3] rounded-lg bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
          <i class="pi pi-image text-2xl text-surface-400" aria-hidden="true" />
        </span>
        <div class="flex items-start gap-1 mt-2">
          <ServiceWorkCaption :work="work" :play-label="t('profile.service_works.watch')" truncate @play="emit('play', $event)" />
          <ReportButton
            v-if="reportable"
            target-type="service_work"
            :target-id="work.id"
            :author-username="username"
            :aria-label="t('profile.service_works.report', { title: work.title })"
            icon-only
          />
        </div>
      </li>
    </ul>

    <ServiceWorkLightbox v-model:work="openWork" />
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { workRows } from '../../../utils/serviceWorks.js'
import ReportButton from '../../Report/ReportButton.vue'
import ServiceWorkCaption from './ServiceWorkCaption.vue'
import ServiceWorkLightbox from './ServiceWorkLightbox.vue'

/**
 * « Réalisations » as a gallery (#1407, #1411): each work's first image, its title and credit under it, and a lightbox
 * through its images. The kinds whose works are pictures show them this way: photographers and graphic designers
 * (#1412).
 */
const props = defineProps({
  title: { type: String, required: true },
  works: { type: Array, required: true },
  /** Whose service: the report names them, and their own works are not reported. */
  username: { type: String, required: true },
  reportable: { type: Boolean, default: false }
})
/** `play` with a work's embed, which the page plays in its own player. */
const emit = defineEmits(['play'])

const { t } = useI18n()

/** The work whose images the lightbox shows: none while it is closed. */
const openWork = ref(null)

const rows = computed(() => workRows(props.works))
</script>
