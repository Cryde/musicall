<template>
  <!-- What shows and which panel: the same controls in the page head and the full screen bar. -->
  <DefineViewSelect v-slot="{ ariaLabel, extraClass }">
    <Select
      :model-value="profile"
      :options="profiles"
      option-label="label"
      option-value="value"
      :class="extraClass"
      :aria-label="ariaLabel"
      @update:model-value="emit('update:profile', $event)"
    />
  </DefineViewSelect>
  <DefinePanelSwitches v-slot="{ labelClass }">
    <div class="inline-flex" role="group" :aria-label="t('band_space_tabs.panel.label')">
      <Button
        v-for="view in panelViews"
        :key="view.tab"
        severity="secondary"
        :outlined="!isPanelOn(view.tab)"
        :aria-pressed="isPanelOn(view.tab)"
        :aria-label="view.label"
        :title="view.hint"
        class="tab-joined"
        @click="emit('toggle-panel', view.tab)"
      >
        <i :class="view.icon" aria-hidden="true" />
        <span :class="labelClass">{{ view.label }}</span>
      </Button>
    </div>
  </DefinePanelSwitches>

  <!-- Phone: a bar of its own, the Band Space header is hidden on this screen. -->
  <header
    v-if="isBelowMd"
    class="flex items-center gap-0.5 h-14 px-1 shrink-0 border-b border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
  >
    <Button icon="pi pi-arrow-left" text rounded severity="secondary" :aria-label="t('band_space_tabs.head.back_to_files')" @click="emit('back')" />
    <div class="flex-1 min-w-0 flex flex-col leading-tight pl-0.5">
      <b class="truncate text-base">{{ title }}</b>
      <small class="text-xs text-surface-500 dark:text-surface-400">{{ t('band_space_tabs.head.phone_meta', { version }) }}</small>
    </div>
    <template v-if="hasScore">
      <Button
        v-for="view in panelViews"
        :key="view.tab"
        :icon="view.icon"
        rounded
        :text="!isPanelOn(view.tab)"
        :severity="isPanelOn(view.tab) ? undefined : 'secondary'"
        :aria-label="view.label"
        :aria-pressed="isPanelOn(view.tab)"
        @click="emit('toggle-panel', view.tab)"
      />
      <Button icon="pi pi-ellipsis-v" text rounded severity="secondary" :aria-label="t('band_space_tabs.options.title')" @click="emit('options')" />
    </template>
  </header>

  <!-- Plein écran: one slim bar instead of the Band Space header and the page head. -->
  <header
    v-else-if="focused"
    class="flex items-center gap-3.5 h-15 pl-3.5 pr-3 shrink-0 border-b border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
  >
    <Button
      :label="t('band_space_tabs.head.leave_full_screen')"
      icon="pi pi-window-minimize"
      severity="secondary"
      outlined
      :title="t('band_space_tabs.head.leave_full_screen_hint')"
      @click="emit('focus', false)"
    />
    <div class="flex-1 min-w-0 flex flex-col leading-tight">
      <b class="truncate text-[1.05rem]">{{ title }}</b>
      <span v-if="hasScore" class="text-sm text-surface-500 dark:text-surface-400 whitespace-nowrap truncate">
        {{ currentSectionName }} · {{ t('band_space_tabs.section.bar', { bar: player.bar + 1 }) }} ·
        <QuarterNote /> = {{ player.heardTempo }}
        <template v-if="player.speed < 1">({{ t('band_space_tabs.player.written', { tempo: player.writtenTempo }) }})</template>
        · {{ player.timeSignature }}
      </span>
    </div>
    <div v-if="hasScore" class="flex items-center gap-1.5">
      <Button icon="pi pi-search-minus" text rounded severity="secondary" :aria-label="t('band_space_tabs.head.zoom_out')" @click="emit('zoom', -1)" />
      <Button icon="pi pi-search-plus" text rounded severity="secondary" :aria-label="t('band_space_tabs.head.zoom_in')" @click="emit('zoom', 1)" />
      <ReuseViewSelect extra-class="mx-1" :aria-label="t('band_space_tabs.head.view')" />
      <ReusePanelSwitches label-class="max-lg:hidden" />
      <Button
        icon="pi pi-expand"
        text
        rounded
        severity="secondary"
        :aria-label="t('band_space_tabs.head.browser_full_screen')"
        :title="t('band_space_tabs.head.browser_full_screen')"
        @click="emit('browser-full-screen')"
      />
    </div>
  </header>

  <!-- Desktop and tablet: the head every Band Space page has. -->
  <div v-else class="flex flex-col gap-1.5 shrink-0">
    <button
      type="button"
      class="self-start inline-flex items-center gap-1.5 border-0 bg-transparent p-0 pr-1.5 text-sm font-medium text-surface-600 dark:text-surface-300 hover:underline cursor-pointer"
      @click="emit('back')"
    >
      <i class="pi pi-arrow-left text-xs" aria-hidden="true" />{{ t('band_space_tabs.head.files') }}
    </button>
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-3 flex-wrap min-w-0">
        <h1 class="m-0 text-2xl font-semibold leading-tight text-surface-900 dark:text-surface-0">{{ title }}</h1>
        <Tag :value="t('band_space_tabs.head.kind')" severity="secondary" />
        <span class="text-sm text-surface-500 dark:text-surface-400">{{ meta }}</span>
      </div>
      <div class="flex items-center gap-2 flex-wrap">
        <template v-if="hasScore">
          <label class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
            {{ t('band_space_tabs.head.view') }}
            <ReuseViewSelect />
          </label>
          <ReusePanelSwitches />
          <Button
            :label="t('band_space_tabs.head.full_screen')"
            icon="pi pi-window-maximize"
            severity="secondary"
            outlined
            :title="t('band_space_tabs.head.full_screen_hint')"
            @click="emit('focus', true)"
          />
        </template>
        <Button
          :label="t('band_space_tabs.head.download')"
          icon="pi pi-download"
          severity="secondary"
          outlined
          @click="emit('download')"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { createReusableTemplate } from '@vueuse/core'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTabLabels } from '../../../composables/useTabLabels.js'
import { PANEL_TABS } from '../../../utils/tabReaderPrefs.js'
import QuarterNote from '../../Tabs/QuarterNote.vue'

/**
 * The reader's head in its three shapes: the Band Space page head on a desktop or a tablet, the slim
 * bar of « Plein écran », and the phone's own bar. The two panel switches are in all of them: the
 * pressed one is the panel on screen, pressing it again hides it.
 */
const props = defineProps({
  title: { type: String, required: true },
  version: { type: Number, default: null },
  meta: { type: String, default: '' },
  player: { type: Object, required: true },
  hasScore: { type: Boolean, required: true },
  profile: { type: String, required: true },
  profiles: { type: Array, required: true },
  panelTab: { type: String, required: true },
  panelVisible: { type: Boolean, required: true },
  focused: { type: Boolean, required: true },
  isBelowMd: { type: Boolean, required: true }
})
const emit = defineEmits([
  'back',
  'update:profile',
  'toggle-panel',
  'focus',
  'zoom',
  'browser-full-screen',
  'download',
  'options'
])
const { t } = useI18n()
const [DefineViewSelect, ReuseViewSelect] = createReusableTemplate()
const [DefinePanelSwitches, ReusePanelSwitches] = createReusableTemplate()
const { sectionName } = useTabLabels()

const panelViews = computed(() => [
  {
    tab: PANEL_TABS.STRUCTURE,
    icon: 'pi pi-list',
    label: t('band_space_tabs.panel.structure'),
    hint: t('band_space_tabs.panel.toggle_structure')
  },
  {
    tab: PANEL_TABS.TRACKS,
    icon: 'pi pi-sliders-v',
    label: t('band_space_tabs.panel.tracks'),
    hint: t('band_space_tabs.panel.toggle_tracks')
  }
])

function isPanelOn(tab) {
  return props.panelVisible && props.panelTab === tab
}

const currentSectionName = computed(() => {
  const section = props.player.sections[props.player.currentSection]
  return section ? sectionName(section) : ''
})
</script>

<style>
/* The two panel switches read as one control. */
.tab-joined:first-child {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}
.tab-joined:last-child {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
  margin-left: -1px;
}
</style>
