<template>
  <div class="min-w-0 flex-1">
    <p class="font-semibold text-surface-900 dark:text-surface-0" :class="{ truncate }">{{ work.title }}</p>
    <p v-if="work.byline" class="text-sm text-surface-600 dark:text-surface-400" :class="{ truncate }">{{ work.byline }}</p>
    <div v-if="work.media || work.domain || $slots.default" class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-2 text-sm">
      <button
        v-if="work.media"
        type="button"
        class="inline-flex items-center gap-1.5 font-medium text-primary-600 dark:text-primary-400 hover:underline"
        @click="emit('play', work.media)"
      >
        <i class="pi pi-play-circle" aria-hidden="true" />{{ playLabel }}
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
      <slot />
    </div>
  </div>
</template>

<script setup>
/** What a work of « Réalisations » says under or beside its images (#1407, #1411), in every layout. */
defineProps({
  /** One of workRows(): with its `byline` and its link's `domain`. */
  work: { type: Object, required: true },
  /** What plays its embed: « Écouter », « Regarder ». */
  playLabel: { type: String, required: true },
  /** One line each, in a narrow gallery tile. */
  truncate: { type: Boolean, default: false }
})
/** `play` with the work's embed, which the page plays in its own player. */
const emit = defineEmits(['play'])
</script>
