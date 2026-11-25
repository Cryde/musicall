<template>
  <template v-if="video">
    <Button
      v-bind="$attrs"
      :label="t('shared.how_to.link')"
      :aria-label="t('shared.how_to.link')"
      :pt="compact ? { label: { class: 'hidden min-[1320px]:inline' } } : undefined"
      icon="pi pi-play-circle"
      size="small"
      text
      aria-haspopup="dialog"
      @click="handleOpen"
    />
    <!-- The dialog renders its content only while open: nothing reaches YouTube before the click, and
         closing it removes the player, which stops the video. -->
    <Dialog
      v-model:visible="isOpen"
      modal
      :draggable="false"
      :header="t(video.titleKey)"
      :style="{ width: '90vw', maxWidth: '900px' }"
    >
      <div class="aspect-video bg-black rounded-lg overflow-hidden">
        <iframe
          :src="video.embedUrl"
          :title="t(video.titleKey)"
          allow="autoplay; encrypted-media; fullscreen; picture-in-picture"
          allowfullscreen
          referrerpolicy="strict-origin-when-cross-origin"
          class="size-full border-0"
        />
      </div>
    </Dialog>
  </template>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { howToVideo } from '../../utils/howToVideo.js'

// « Comment ça marche »: plays a how-to video in a dialog, when one exists in the reader's language.
const props = defineProps({
  /** A key of the catalogue in utils/howToVideo.js. */
  name: { type: String, required: true },
  /** Where the link sits, so the analytics tell which spot gets used. */
  placement: { type: String, required: true },
  /** Icon only on a narrower window, for a toolbar that would otherwise wrap its other buttons: the
   * Tâches toolbar fits the label on one line from 1311px with the sidebar open. */
  compact: { type: Boolean, default: false }
})

// Two roots (the link and its dialog): a class from the page goes on the link.
defineOptions({ inheritAttrs: false })

const { t, locale } = useI18n()
const isOpen = ref(false)
const video = computed(() => howToVideo(props.name, locale.value))

function handleOpen() {
  isOpen.value = true
  trackUmamiEvent('howto-video-open', { video: props.name, placement: props.placement })
}
</script>
