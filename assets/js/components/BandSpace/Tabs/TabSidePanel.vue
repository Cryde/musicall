<template>
  <!-- The panel's content, written once for the phone's sheet and the desktop's panel. -->
  <DefineContent v-slot="{ tipClass }">
    <template v-if="tab === PANEL_TABS.STRUCTURE">
      <TabStructureList
        :sections="player.sections"
        :current-section="player.currentSection"
        :looped-section="player.loopedSection"
        @go="player.goToSection($event)"
        @loop="player.loopSection($event)"
      />
      <p :class="tipClass">
        <i class="pi pi-info-circle mt-0.5 text-xs max-md:hidden" aria-hidden="true" />
        <i18n-t keypath="band_space_tabs.panel.structure_tip" tag="span">
          <template #icon><i class="pi pi-replay text-[0.7rem]" aria-hidden="true" /></template>
        </i18n-t>
      </p>
    </template>
    <template v-else>
      <TabTrackList
        :tracks="tracks"
        :shown="shown"
        :muted="player.muted"
        :soloed="player.soloed"
        :volumes="player.volumes"
        @toggle-shown="emit('toggle-shown', $event)"
        @toggle-mute="player.toggleMute($event)"
        @toggle-solo="player.toggleSolo($event)"
        @volume="(index, value) => player.setVolume(index, value)"
      />
      <p :class="tipClass">
        <i class="pi pi-info-circle mt-0.5 text-xs max-md:hidden" aria-hidden="true" />
        <span>{{ t('band_space_tabs.panel.tracks_tip') }}</span>
      </p>
    </template>
  </DefineContent>

  <BottomSheet v-if="isBelowMd" :visible="visible" :title="title" @update:visible="(open) => !open && emit('close')">
    <template #header>
      <SelectButton
        :model-value="tab"
        :options="tabOptions"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        class="flex-1 [&>*]:flex-1"
        :aria-label="t('band_space_tabs.panel.label')"
        @update:model-value="emit('update:tab', $event)"
      />
    </template>
    <ReuseContent tip-class="m-0 px-5 pt-2 text-xs text-surface-500 dark:text-surface-400" />
  </BottomSheet>

  <template v-else-if="visible">
    <!-- Below a desktop the panel covers the score: a tap anywhere on the score puts it away. -->
    <div v-if="isBelowLg" class="absolute inset-0 z-[9]" aria-hidden="true" @click="emit('close')" />
    <aside
      class="w-[300px] shrink-0 flex flex-col overflow-y-auto bg-surface-0 dark:bg-surface-900 border-l border-surface-200 dark:border-surface-700 max-lg:absolute max-lg:inset-y-0 max-lg:right-0 max-lg:z-10 max-lg:shadow-xl"
      :aria-label="t('band_space_tabs.panel.label')"
    >
      <div class="sticky top-0 z-[2] flex items-center gap-2 pl-4 pr-2 py-2 bg-surface-0 dark:bg-surface-900">
        <h2 class="m-0 text-[0.95rem] font-bold text-surface-900 dark:text-surface-0">{{ title }}</h2>
        <span class="flex-1 text-sm text-surface-500 dark:text-surface-400">
          {{
            tab === PANEL_TABS.STRUCTURE
              ? t('band_space_tabs.panel.section_count', player.sections.length)
              : t('band_space_tabs.panel.track_count', tracks.length)
          }}
        </span>
        <Button
          icon="pi pi-times"
          text
          rounded
          severity="secondary"
          :aria-label="t('band_space_tabs.panel.hide')"
          @click="emit('close')"
        />
      </div>
      <ReuseContent tip-class="mt-auto mx-4 mb-3.5 pt-3.5 text-xs leading-relaxed text-surface-500 dark:text-surface-400 flex gap-2" />
    </aside>
  </template>
</template>

<script setup>
import { createReusableTemplate } from '@vueuse/core'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { PANEL_TABS } from '../../../utils/tabReaderPrefs.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import TabStructureList from './TabStructureList.vue'
import TabTrackList from './TabTrackList.vue'

/**
 * The reader's right panel, showing the song's structure or its tracks: docked beside the score on a
 * desktop, over the score on a tablet, a sheet with both tabs on a phone.
 */
const props = defineProps({
  player: { type: Object, required: true },
  shown: { type: Array, required: true },
  tab: { type: String, required: true },
  visible: { type: Boolean, required: true },
  isBelowMd: { type: Boolean, required: true },
  isBelowLg: { type: Boolean, required: true }
})
const emit = defineEmits(['update:tab', 'close', 'toggle-shown'])
const { t } = useI18n()
const [DefineContent, ReuseContent] = createReusableTemplate()

const tracks = computed(() => props.player.score?.tracks ?? [])
const title = computed(() =>
  props.tab === PANEL_TABS.STRUCTURE
    ? t('band_space_tabs.panel.structure')
    : t('band_space_tabs.panel.tracks')
)
const tabOptions = computed(() => [
  { label: t('band_space_tabs.panel.structure'), value: PANEL_TABS.STRUCTURE },
  { label: t('band_space_tabs.panel.tracks'), value: PANEL_TABS.TRACKS }
])
</script>
