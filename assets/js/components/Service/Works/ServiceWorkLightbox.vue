<template>
  <Galleria
    v-if="work"
    v-model:visible="isVisible"
    v-model:active-index="activeIndex"
    :value="work.images"
    full-screen
    circular
    show-item-navigators
    :show-thumbnails="false"
    :show-item-navigators-on-hover="false"
    :aria-label="work.title"
    :pt="{ mask: { class: 'bg-black/90!' } }"
  >
    <template #item="{ item }">
      <img
        :src="item.url"
        :alt="t('profile.service_works.image_alt', { number: activeIndex + 1, title: work.title })"
        class="block max-h-[80vh] max-w-[90vw] object-contain"
      />
    </template>
    <template #caption>
      <p class="font-semibold">{{ work.title }}</p>
      <p v-if="caption" class="text-sm opacity-80">{{ caption }}</p>
    </template>
  </Galleria>
</template>

<script setup>
import Galleria from 'primevue/galleria'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * One work's images in full screen (#1407), its title and credit under them: a gallery's pictures, a site's screenshots
 * (#1412). `work` is one of workRows(), from its first image, and null while closed, which closing sets it back to.
 */
const work = defineModel('work', { type: Object, default: null })

const { t } = useI18n()

const activeIndex = ref(0)

// Each work opens on its first image.
watch(work, () => {
  activeIndex.value = 0
})

const isVisible = computed({
  get: () => work.value !== null,
  set: (visible) => {
    if (!visible) work.value = null
  }
})
/** « Mona Lisa Overdrive, live · 2025 · 2 sur 5 ». */
const caption = computed(() =>
  [
    work.value?.byline,
    work.value?.images.length > 1
      ? t('profile.service_works.position', {
          current: activeIndex.value + 1,
          total: work.value.images.length
        })
      : ''
  ]
    .filter(Boolean)
    .join(' · ')
)
</script>
