<template>
  <div class="py-6 md:py-10">
    <div class="flex flex-col gap-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
          Paramètres
        </h1>
        <p class="text-surface-500 dark:text-surface-400">
          Gérez les paramètres de votre compte
        </p>
      </div>

      <div class="flex flex-col md:flex-row gap-6">
        <!-- Vertical tabs sidebar -->
        <div class="md:w-64 shrink-0">
          <div class="bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-2">
            <button
              v-for="tab in tabs"
              :key="tab.value"
              @click="handleTabChange(tab.value)"
              class="flex items-center gap-3 w-full px-4 py-3 text-sm font-medium rounded-lg transition-colors"
              :class="[
                activeTab === tab.value
                  ? 'bg-primary/10 text-primary'
                  : 'text-surface-600 dark:text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800'
              ]"
            >
              <i :class="tab.icon"></i>
              <span>{{ tab.label }}</span>
            </button>
          </div>
        </div>

        <!-- Content area -->
        <div class="flex-1 bg-surface-0 dark:bg-surface-900 rounded-xl shadow-sm p-6">
          <router-view />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTitle } from '@vueuse/core'
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserSettingsStore } from '../../../store/user/settings.js'

useTitle('Paramètres - MusicAll')

const route = useRoute()
const router = useRouter()
const userSettingsStore = useUserSettingsStore()

const tabs = [
  { value: 'general', label: 'Général', icon: 'pi pi-user', route: 'app_user_settings' },
  {
    value: 'profile',
    label: 'Profil',
    icon: 'pi pi-id-card',
    route: 'app_user_settings_profile'
  },
  {
    value: 'password',
    label: 'Mot de passe',
    icon: 'pi pi-lock',
    route: 'app_user_settings_password'
  },
  {
    value: 'notifications',
    label: 'Notifications',
    icon: 'pi pi-bell',
    route: 'app_user_settings_notifications'
  },
  {
    value: 'privacy',
    label: 'Confidentialité',
    icon: 'pi pi-shield',
    route: 'app_user_settings_privacy'
  }
]

const activeTab = computed(() => {
  const currentRoute = route.name
  // Handle profile sub-routes
  if (currentRoute?.startsWith('app_user_settings_profile')) {
    return 'profile'
  }
  const tab = tabs.find((t) => t.route === currentRoute)
  return tab?.value || 'general'
})

function handleTabChange(value) {
  const tab = tabs.find((t) => t.value === value)
  if (tab) {
    router.push({ name: tab.route })
  }
}

onMounted(() => {
  userSettingsStore.loadUserProfile()
})
</script>
