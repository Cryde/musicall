<template>
  <section class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-5 md:p-6">
    <h2 class="font-semibold text-surface-900 dark:text-surface-0 mb-4 flex items-center gap-2">
      <i class="pi pi-desktop text-surface-400" aria-hidden="true" />{{ title }}
    </h2>
    <ul class="grid md:grid-cols-2 gap-x-5 gap-y-6">
      <li v-for="work in rows" :key="work.id" class="flex flex-col min-w-0">
        <!-- A browser window: the site's address over its first screenshot -->
        <div class="rounded-lg overflow-hidden border border-surface-200 dark:border-surface-700">
          <div class="flex items-center gap-1.5 px-3 py-1.5 bg-surface-100 dark:bg-surface-800" aria-hidden="true">
            <span v-for="dot in 3" :key="dot" class="size-2 shrink-0 rounded-full bg-surface-300 dark:bg-surface-600" />
            <span v-if="work.domain" class="ml-2 truncate text-xs text-surface-500 dark:text-surface-400">{{ work.domain }}</span>
          </div>
          <button
            v-if="work.images.length"
            type="button"
            class="group relative block w-full aspect-[16/10] overflow-hidden bg-surface-50 dark:bg-surface-800 cursor-pointer"
            :aria-label="t('profile.service_works.open_screenshots', { title: work.title, count: work.images.length }, work.images.length)"
            @click="openWork = work"
          >
            <img :src="work.images[0].url" alt="" class="w-full h-full object-cover object-top transition-transform duration-300 group-hover:scale-105" loading="lazy" />
            <span v-if="work.images.length > 1" class="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-md bg-black/60 px-2 py-0.5 text-xs font-semibold text-white" aria-hidden="true">
              <i class="pi pi-images text-[10px]" />{{ work.images.length }}
            </span>
          </button>
          <span v-else class="aspect-[16/10] bg-surface-50 dark:bg-surface-800 flex items-center justify-center">
            <i class="pi pi-desktop text-2xl text-surface-400" aria-hidden="true" />
          </span>
        </div>
        <div class="flex items-start gap-1 mt-2">
          <ServiceWorkCaption :work="work" :play-label="t('profile.service_works.watch')" @play="emit('play', $event)" />
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
 * « Réalisations » as sites (#1412): each one's screenshots in a browser window, its address under them as a link the
 * visitor opens. The screenshots are the ones its member uploaded; nothing is ever fetched from the site.
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

/** The work whose screenshots the lightbox shows: none while it is closed. */
const openWork = ref(null)

const rows = computed(() => workRows(props.works))
</script>
