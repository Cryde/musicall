<template>
  <div v-if="preview" class="mt-1 w-96 max-w-full" :class="{ 'ml-auto': alignEnd }">
    <!-- The box is sized before the player loads, so the conversation never jumps under the reader. -->
    <div
      v-if="preview.embedUrl"
      class="overflow-hidden rounded-xl bg-surface-200 dark:bg-surface-700"
      :class="{ 'aspect-video': embedHeightPx === null }"
      :style="embedHeightPx === null ? null : { height: `${embedHeightPx}px` }"
    >
      <iframe
        :src="preview.embedUrl"
        :title="preview.title"
        :allow="EMBED_PERMISSIONS[preview.provider]"
        :allowfullscreen="preview.provider === 'youtube'"
        class="size-full border-0"
        loading="lazy"
        referrerpolicy="strict-origin-when-cross-origin"
      />
    </div>

    <!-- Bandcamp only. Its embed needs a numeric release id the page URL does not carry, so a card
         that opens the release is as far as this can go without asking Bandcamp for it. -->
    <a
      v-else
      :href="preview.href"
      :aria-label="`${preview.title} : ouvrir ${displayedHref} dans un nouvel onglet`"
      target="_blank"
      rel="noopener noreferrer"
      class="flex items-center gap-3 rounded-xl border border-surface-200 bg-surface-0 p-3 transition-colors hover:border-primary-500 dark:border-surface-600 dark:bg-surface-800"
    >
      <span
        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
      >
        <i class="pi pi-play-circle text-xl" aria-hidden="true" />
      </span>
      <span class="min-w-0 flex-1">
        <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0">
          {{ preview.title }}
        </span>
        <span class="block truncate text-xs text-surface-500 dark:text-surface-400">
          {{ displayedHref }}
        </span>
      </span>
      <i
        class="pi pi-external-link shrink-0 text-xs text-surface-500 dark:text-surface-400"
        aria-hidden="true"
      />
    </a>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { findMusicLinkPreview } from '../../utils/musicLinkPreview.js'

const props = defineProps({
  content: { type: String, default: '' },
  /** Own messages sit on the right, so their preview does too. Same prop as ChatMessageReactions. */
  alignEnd: { type: Boolean, default: false }
})

// Only what each player actually needs. Absent means the browser grants a cross-origin frame
// nothing, which is what SoundCloud runs on.
const EMBED_PERMISSIONS = {
  youtube: 'encrypted-media; fullscreen; picture-in-picture',
  spotify: 'encrypted-media'
}

// YouTube is the one player that scales with its box; the others are fixed-height widgets that a
// ratio would crop. These are the heights each service ships its own embed code with.
const EMBED_HEIGHT_PX = {
  'spotify:track': 152,
  'spotify:album': 352,
  'spotify:playlist': 352,
  'spotify:artist': 352,
  'soundcloud:track': 166
}

const preview = computed(() => findMusicLinkPreview(props.content))

const embedHeightPx = computed(
  () => EMBED_HEIGHT_PX[`${preview.value?.provider}:${preview.value?.kind}`] ?? null
)

const displayedHref = computed(() => preview.value?.href.replace(/^https:\/\//, '') ?? '')
</script>
