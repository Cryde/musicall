import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import notificationPreferencesApi from '../../api/user/notificationPreferences.js'

export const useNotificationPreferencesStore = defineStore('notificationPreferences', () => {
  const preferences = ref(null)
  const isLoading = ref(false)
  const isUpdating = ref(false)

  async function loadPreferences() {
    isLoading.value = true
    try {
      preferences.value = await notificationPreferencesApi.getPreferences()
      return preferences.value
    } finally {
      isLoading.value = false
    }
  }

  async function updatePreferences(data) {
    isUpdating.value = true
    try {
      preferences.value = await notificationPreferencesApi.updatePreferences(data)
      return preferences.value
    } finally {
      isUpdating.value = false
    }
  }

  function clear() {
    preferences.value = null
  }

  return {
    preferences: readonly(preferences),
    isLoading: readonly(isLoading),
    isUpdating: readonly(isUpdating),
    loadPreferences,
    updatePreferences,
    clear
  }
})
