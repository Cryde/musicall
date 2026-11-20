<template>
  <div>
    <div
      v-for="track in tracks"
      :key="track.index"
      class="grid grid-cols-[4px_minmax(0,1fr)_auto] gap-x-2.5 gap-y-2 px-3.5 py-2.5 border-t border-surface-200 dark:border-surface-700"
    >
      <span class="row-span-2 rounded" :style="{ background: trackColor(track.index) }" aria-hidden="true" />
      <div class="flex flex-col min-w-0 leading-tight">
        <span
          class="text-sm font-semibold truncate"
          :class="[
            isSilent(track.index) ? 'line-through decoration-amber-500 text-surface-500' : '',
            shown.includes(track.index) ? 'text-surface-900 dark:text-surface-0' : 'text-surface-600 dark:text-surface-300 font-medium'
          ]"
        >{{ track.name }}</span>
        <span class="text-xs text-surface-500 dark:text-surface-400 truncate">{{ kindOf(track) }}</span>
      </div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="tab-ms"
          :class="shown.includes(track.index) ? 'tab-ms-shown' : ''"
          :aria-pressed="shown.includes(track.index)"
          :aria-label="t('band_space_tabs.track.show', { name: track.name })"
          @click="emit('toggle-shown', track.index)"
        >
          <i :class="shown.includes(track.index) ? 'pi pi-eye' : 'pi pi-eye-slash'" aria-hidden="true" />
        </button>
        <button
          type="button"
          class="tab-ms"
          :class="muted.includes(track.index) ? 'tab-ms-muted' : ''"
          :aria-pressed="muted.includes(track.index)"
          :aria-label="t('band_space_tabs.track.mute', { name: track.name })"
          @click="emit('toggle-mute', track.index)"
        >
          {{ t('band_space_tabs.track.mute_short') }}
        </button>
        <button
          type="button"
          class="tab-ms"
          :class="soloed.includes(track.index) ? 'tab-ms-solo' : ''"
          :aria-pressed="soloed.includes(track.index)"
          :aria-label="t('band_space_tabs.track.solo', { name: track.name })"
          @click="emit('toggle-solo', track.index)"
        >
          {{ t('band_space_tabs.track.solo_short') }}
        </button>
      </div>
      <div class="col-start-2 col-span-2 flex items-center gap-2 text-surface-500">
        <i class="pi pi-volume-down text-xs" aria-hidden="true" />
        <Slider
          :model-value="volumes[track.index] ?? 1"
          :min="0"
          :max="1.5"
          :step="0.05"
          class="flex-1"
          :aria-label="t('band_space_tabs.track.volume', { name: track.name })"
          @update:model-value="emit('volume', track.index, $event)"
        />
        <i class="pi pi-volume-up text-xs" aria-hidden="true" />
      </div>
    </div>
  </div>
</template>

<script setup>
import Slider from 'primevue/slider'
import { useI18n } from 'vue-i18n'
import { TRACK_FAMILIES, trackColor, trackFamily } from '../../../utils/tabTracks.js'

/**
 * The file's tracks: the eye picks what is drawn (several at once), M mutes a part so a member plays
 * it themselves, S plays it alone, and each has its volume.
 */
const props = defineProps({
  tracks: { type: Array, required: true },
  shown: { type: Array, required: true },
  muted: { type: Array, required: true },
  soloed: { type: Array, required: true },
  volumes: { type: Object, required: true }
})
const emit = defineEmits(['toggle-shown', 'toggle-mute', 'toggle-solo', 'volume'])
const { t } = useI18n()

const KIND_KEYS = {
  [TRACK_FAMILIES.GUITAR]: 'band_space_tabs.track.guitar',
  [TRACK_FAMILIES.BASS]: 'band_space_tabs.track.bass',
  [TRACK_FAMILIES.DRUMS]: 'band_space_tabs.track.drums',
  [TRACK_FAMILIES.OTHER]: 'band_space_tabs.track.other'
}

function kindOf(track) {
  const staff = track.staves[0]
  const family = trackFamily(track.playbackInfo.program, staff.isPercussion)
  return t(KIND_KEYS[family], { strings: staff.tuning?.length ?? 0 })
}

/** Muted, or left out while another track plays solo. */
function isSilent(index) {
  return props.muted.includes(index) || (props.soloed.length > 0 && !props.soloed.includes(index))
}
</script>

<style>
/* The eye, M and S: small square switches, coloured when on. */
.tab-ms {
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 0.375rem;
  border: 1px solid var(--p-surface-300);
  background: var(--p-surface-0);
  color: var(--p-surface-600);
  font-size: 0.75rem;
  font-weight: 800;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.dark-mode .tab-ms {
  border-color: var(--p-surface-600);
  background: var(--p-surface-900);
  color: var(--p-surface-300);
}
.tab-ms.tab-ms-shown {
  background: var(--p-primary-50);
  border-color: var(--p-primary-color);
  color: var(--p-primary-700);
}
.dark-mode .tab-ms.tab-ms-shown {
  background: color-mix(in srgb, var(--p-primary-color) 18%, transparent);
  color: var(--p-primary-200);
}
.tab-ms.tab-ms-muted {
  background: #d97706;
  border-color: #d97706;
  color: #1c1917;
}
.tab-ms.tab-ms-solo {
  background: #0f766e;
  border-color: #0f766e;
  color: #ffffff;
}
.dark-mode .tab-ms.tab-ms-muted {
  background: #fbbf24;
  border-color: #fbbf24;
}
.dark-mode .tab-ms.tab-ms-solo {
  background: #2dd4bf;
  border-color: #2dd4bf;
  color: #042f2e;
}
</style>
