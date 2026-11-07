<template>
  <section
    v-if="tracks.length"
    ref="cardRef"
    class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm overflow-hidden"
    aria-labelledby="profile-listen-title"
  >
    <h2 id="profile-listen-title" class="m-0 px-5 pt-4 pb-3 text-[15px] font-bold text-surface-900 dark:text-surface-0">
      {{ t('profile.public.listen.title', { name: displayName }) }}
    </h2>

    <div class="px-4">
      <!-- Nothing from the platforms loads before a click, as the privacy page says. -->
      <template v-if="isPlaying">
        <div class="rounded-lg overflow-hidden bg-black" :style="{ height: `${playerHeight}px` }">
          <MediaEmbedFrame :key="current.id" :media="current" autoplay compact />
        </div>
        <div class="flex items-center gap-1 pt-2">
          <template v-if="tracks.length > 1">
            <Button
              icon="pi pi-chevron-left"
              text
              rounded
              size="small"
              :aria-label="t('profile.public.listen.previous')"
              @click="step(-1)"
            />
            <span class="text-xs text-surface-500 dark:text-surface-400">{{ currentIndex + 1 }} / {{ tracks.length }}</span>
            <Button
              icon="pi pi-chevron-right"
              text
              rounded
              size="small"
              :aria-label="t('profile.public.listen.next')"
              @click="step(1)"
            />
          </template>
          <Button
            ref="stopButton"
            icon="pi pi-stop"
            :label="t('profile.public.listen.stop_short')"
            text
            size="small"
            severity="secondary"
            class="ml-auto"
            :aria-label="t('profile.public.listen.stop')"
            @click="stop"
          />
        </div>
      </template>
      <div v-else class="flex items-center gap-3 p-3 rounded-lg bg-surface-50 dark:bg-surface-800">
        <ProfileListenThumbnail :track="current" large />
        <span class="flex-1 min-w-0">
          <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0 truncate">{{ trackLabel(current) }}</span>
          <span class="block text-xs text-surface-500 dark:text-surface-400">{{ current.platform_label }}</span>
        </span>
        <Button
          ref="playButton"
          icon="pi pi-play"
          rounded
          :aria-label="t('profile.public.listen.play_track', { title: trackLabel(current) })"
          @click="play(currentIndex)"
        />
      </div>
    </div>

    <ol class="list-none m-0 mt-3 p-0 border-t border-surface-200 dark:border-surface-700">
      <li v-for="(track, index) in tracks" :key="track.id" class="border-b last:border-b-0 border-surface-200 dark:border-surface-700">
        <button
          type="button"
          class="w-full flex items-center gap-3 px-5 py-2.5 text-left text-sm cursor-pointer transition-colors"
          :class="isCurrent(index)
            ? 'bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300'
            : 'text-surface-700 dark:text-surface-200 hover:bg-surface-50 dark:hover:bg-surface-800'"
          :aria-current="isCurrent(index) ? 'true' : undefined"
          :aria-label="t('profile.public.listen.play_track', { title: trackLabel(track) })"
          @click="play(index)"
        >
          <i :class="isCurrent(index) ? 'pi pi-volume-up' : 'pi pi-play text-surface-400'" aria-hidden="true" />
          <span class="flex-1 min-w-0 truncate">{{ trackLabel(track) }}</span>
          <span class="text-xs text-surface-500 dark:text-surface-400">{{ track.platform_label }}</span>
        </button>
      </li>
    </ol>

    <!-- While the card is out of view: what is playing, the next track and a way to stop it. -->
    <Teleport to="body">
      <div
        v-if="isPlaying && !isCardVisible"
        role="region"
        :aria-label="t('profile.public.listen.mini_player')"
        class="fixed inset-x-0 bottom-0 z-40 flex items-center gap-3 px-4 pt-2.5 pb-[calc(0.625rem+env(safe-area-inset-bottom))] bg-surface-0 dark:bg-surface-900 border-t border-surface-200 dark:border-surface-700 shadow-lg"
      >
        <ProfileListenThumbnail :track="current" />
        <span class="flex-1 min-w-0">
          <span class="block text-sm font-semibold text-surface-900 dark:text-surface-0 truncate">{{ trackLabel(current) }}</span>
          <span class="block text-xs text-surface-500 dark:text-surface-400">{{ current.platform_label }}</span>
        </span>
        <Button
          v-if="tracks.length > 1"
          icon="pi pi-step-forward"
          text
          rounded
          :aria-label="t('profile.public.listen.next')"
          @click="step(1)"
        />
        <Button icon="pi pi-times" text rounded :aria-label="t('profile.public.listen.stop')" @click="stop" />
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { useIntersectionObserver } from '@vueuse/core'
import Button from 'primevue/button'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { mediaEmbedHeight } from '../../../utils/mediaEmbed.js'
import { listenPlaylist, stepIndex, trackIndex, trackLabel } from '../../../utils/profileListen.js'
import MediaEmbedFrame from '../../Media/MediaEmbedFrame.vue'
import ProfileListenThumbnail from './ProfileListenThumbnail.vue'

/**
 * « Écouter {name} » (#1317): the member's media as a playlist the visitor picks from. The platform's own
 * player plays the track; stopping unmounts it, the only way to stop it without the platforms' scripts.
 */
const props = defineProps({
  profile: { type: Object, required: true },
  displayName: { type: String, required: true }
})

const { t } = useI18n()
const tracks = computed(() => listenPlaylist(props.profile))
// The track by id, not by place: a list that changes must not start another track on its own.
const currentId = ref(null)
const isPlaying = ref(false)
const currentIndex = computed(() => Math.max(trackIndex(tracks.value, currentId.value), 0))
const current = computed(() => tracks.value[currentIndex.value])
const playerHeight = computed(() => mediaEmbedHeight(current.value))

const cardRef = ref(null)
const playButton = ref(null)
const stopButton = ref(null)
const isCardVisible = ref(true)
useIntersectionObserver(cardRef, ([entry]) => {
  isCardVisible.value = entry?.isIntersecting ?? true
})

const isCurrent = (index) => isPlaying.value && index === currentIndex.value

// The control a click removes gives its focus to the one that takes its place, without scrolling the page.
async function focusAfterRender(buttonRef) {
  await nextTick()
  buttonRef.value?.$el?.focus({ preventScroll: true })
}

function play(index) {
  const wasPlaying = isPlaying.value
  currentId.value = tracks.value[index]?.id ?? null
  isPlaying.value = true
  if (!wasPlaying) focusAfterRender(stopButton)
}

function step(direction) {
  play(stepIndex(tracks.value.length, currentIndex.value, direction))
}

function stop() {
  isPlaying.value = false
  focusAfterRender(playButton)
}

// Another member, or the playing track gone from the list: silent again, nothing starts without a click.
watch(
  () => props.profile.username,
  () => {
    currentId.value = null
    isPlaying.value = false
  }
)
watch(tracks, (list) => {
  if (isPlaying.value && trackIndex(list, currentId.value) === -1) {
    currentId.value = null
    isPlaying.value = false
  }
})
</script>
