<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
      <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('profile.settings.profile.title') }}
      </h2>
      <p class="text-surface-500 dark:text-surface-400 text-sm">
        {{ t('profile.settings.profile.subtitle') }}
      </p>
    </div>

    <!-- Sub-tabs -->
    <div class="border-b border-surface-200 dark:border-surface-700">
      <nav class="flex gap-4">
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="handleTabChange(tab.value)"
          class="px-1 py-3 text-sm font-medium border-b-2 transition-colors -mb-px"
          :class="[
            activeTab === tab.value
              ? 'border-primary text-primary'
              : 'border-transparent text-surface-500 dark:text-surface-400 hover:text-surface-700 dark:hover:text-surface-300'
          ]"
        >
          {{ t(tab.labelKey) }}
        </button>
      </nav>
    </div>

    <!-- Content -->
    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const tabs = [
  {
    value: 'general',
    labelKey: 'profile.settings.profile.tabs.general',
    route: 'app_user_settings_profile'
  },
  {
    value: 'musician',
    labelKey: 'profile.settings.profile.tabs.musician',
    route: 'app_user_settings_profile_musician'
  },
  {
    value: 'teacher',
    labelKey: 'profile.settings.profile.tabs.teacher',
    route: 'app_user_settings_profile_teacher'
  },
  {
    value: 'privacy',
    labelKey: 'profile.settings.profile.tabs.privacy',
    route: 'app_user_settings_profile_privacy'
  }
]

const activeTab = computed(() => {
  const currentRoute = route.name
  if (currentRoute === 'app_user_settings_profile_privacy') {
    return 'privacy'
  }
  if (currentRoute === 'app_user_settings_profile_musician') {
    return 'musician'
  }
  if (currentRoute === 'app_user_settings_profile_teacher') {
    return 'teacher'
  }
  return 'general'
})

function handleTabChange(value) {
  const tab = tabs.find((t) => t.value === value)
  if (tab) {
    router.push({ name: tab.route })
  }
}
</script>
