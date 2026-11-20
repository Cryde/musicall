<template>
  <BottomSheet v-model:visible="visible" :title="t('band_space_tabs.options.title')">
    <div class="flex flex-col gap-3 px-5 pb-2">
      <h3 class="m-0 text-sm font-semibold text-surface-700 dark:text-surface-200 flex items-center gap-2">
        <i class="pi pi-gauge" aria-hidden="true" />{{ t('band_space_tabs.options.speed') }}
      </h3>
      <TabSpeedPanel
        :speed="player.speed"
        :trainer="player.trainer"
        @update:speed="player.setSpeed($event)"
        @toggle-trainer="player.toggleTrainer()"
      />
      <label class="flex items-center justify-between gap-3 min-h-12 text-[0.95rem] cursor-pointer">
        <span class="flex items-center gap-2.5"><MetronomeIcon class="text-surface-500" />{{ t('band_space_tabs.player.metronome') }}</span>
        <ToggleSwitch :model-value="player.metronome" @update:model-value="player.toggleMetronome()" />
      </label>
      <label class="flex items-center justify-between gap-3 min-h-12 text-[0.95rem] cursor-pointer">
        <span class="flex items-center gap-2.5">
          <i class="pi pi-stopwatch text-surface-500" aria-hidden="true" />{{ t('band_space_tabs.options.count_in') }}
        </span>
        <ToggleSwitch :model-value="player.countIn" @update:model-value="player.toggleCountIn()" />
      </label>
      <h3 class="m-0 text-sm font-semibold text-surface-700 dark:text-surface-200 flex items-center gap-2">
        <i class="pi pi-eye" aria-hidden="true" />{{ t('band_space_tabs.head.view') }}
      </h3>
      <SelectButton
        :model-value="profile"
        :options="profiles"
        option-label="short"
        option-value="value"
        :allow-empty="false"
        class="w-full [&>*]:flex-1 [&>*]:!px-1.5 [&>*]:text-[0.8rem] [&>*]:whitespace-nowrap"
        :aria-label="t('band_space_tabs.head.view')"
        @update:model-value="emit('update:profile', $event)"
      />
    </div>
    <template #footer>
      <SheetAction icon="pi pi-download" @click="emit('download')">{{ t('band_space_tabs.options.download') }}</SheetAction>
    </template>
  </BottomSheet>
</template>

<script setup>
import SelectButton from 'primevue/selectbutton'
import ToggleSwitch from 'primevue/toggleswitch'
import { useI18n } from 'vue-i18n'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'
import MetronomeIcon from '../../Tabs/MetronomeIcon.vue'
import TabSpeedPanel from './TabSpeedPanel.vue'

/** The phone's « … »: speed and trainer, metronome, count-in, what the score shows, the download. */
defineProps({
  player: { type: Object, required: true },
  profile: { type: String, required: true },
  /** `{ value, label, short }`: the switch shows the short labels. */
  profiles: { type: Array, required: true }
})
const emit = defineEmits(['update:profile', 'download'])
const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()
</script>
