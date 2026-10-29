<template>
  <div
    role="img"
    :aria-label="t('home.band_space_presentation.mobile_app.mockup_aria_label')"
    class="w-64 shrink-0 rounded-[2.5rem] border-[10px] border-surface-900 dark:border-surface-700 bg-gradient-to-b from-primary-700 to-primary-950 px-3 pt-8 pb-12 shadow-xl"
  >
    <div class="flex flex-col items-center text-white mb-6">
      <span class="text-5xl font-light tabular-nums">20:14</span>
      <span class="text-xs opacity-80">{{ t('home.band_space_presentation.mobile_app.mock.date') }}</span>
    </div>
    <div class="flex flex-col gap-2">
      <div v-for="notification in notifications" :key="notification.key" class="rounded-2xl bg-white/90 dark:bg-surface-900/90 p-3 flex flex-col gap-1">
        <div class="flex items-center gap-1.5 text-[11px] text-surface-600 dark:text-surface-400">
          <span class="w-4 h-4 rounded bg-primary-700 text-white inline-flex items-center justify-center text-[8px] font-extrabold">M</span>
          <span>MusicAll</span>
          <span class="ml-auto">{{ notification.when }}</span>
        </div>
        <span class="text-sm font-semibold text-surface-900 dark:text-surface-0">{{ t('home.band_space_presentation.mobile_app.mock.band_name') }}</span>
        <span class="text-xs leading-snug text-surface-700 dark:text-surface-200">{{ notification.body }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// Worded as PushContentBuilder words the real ones: the band as the title, the sentence below.
const notifications = computed(() => [
  {
    key: 'chat',
    when: t('home.band_space_presentation.mobile_app.mock.now'),
    body: t('home.band_space_presentation.mobile_app.mock.chat')
  },
  {
    key: 'agenda',
    when: t('home.band_space_presentation.mobile_app.mock.minutes_ago', { count: 12 }),
    body: t('home.band_space_presentation.mobile_app.mock.agenda')
  },
  {
    key: 'task',
    when: t('home.band_space_presentation.mobile_app.mock.minutes_ago', { count: 40 }),
    body: t('home.band_space_presentation.mobile_app.mock.task')
  }
])
</script>
