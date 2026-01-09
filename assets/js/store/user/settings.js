import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import securityApi from '../../api/user/security.js'
import { useUserSecurityStore } from './security.js'

export const useUserSettingsStore = defineStore('userSettings', () => {
  const userSecurityStore = useUserSecurityStore()

  const userProfile = ref(null)
  const isLoading = ref(false)
  const isChangingPassword = ref(false)
  const isChangingPicture = ref(false)
  const isDeletingPicture = ref(false)

  async function loadUserProfile() {
    isLoading.value = true
    try {
      userProfile.value = await securityApi.getSelf()
    } finally {
      isLoading.value = false
    }
  }

  async function changePassword({ oldPassword, newPassword }) {
    isChangingPassword.value = true
    try {
      await securityApi.changePassword({ oldPassword, newPassword })
    } finally {
      isChangingPassword.value = false
    }
  }

  async function changeProfilePicture(formData) {
    isChangingPicture.value = true
    try {
      await securityApi.changeProfilePicture(formData)
      await loadUserProfile()
      // Also refresh the security store's profile so navbar avatar updates
      await userSecurityStore.refreshUserProfile()
    } finally {
      isChangingPicture.value = false
    }
  }

  async function deleteProfilePicture() {
    isDeletingPicture.value = true
    try {
      await securityApi.deleteProfilePicture()
      await loadUserProfile()
      // Also refresh the security store's profile so navbar avatar updates
      await userSecurityStore.refreshUserProfile()
    } finally {
      isDeletingPicture.value = false
    }
  }

  function clear() {
    userProfile.value = null
  }

  return {
    userProfile: readonly(userProfile),
    isLoading: readonly(isLoading),
    isChangingPassword: readonly(isChangingPassword),
    isChangingPicture: readonly(isChangingPicture),
    isDeletingPicture: readonly(isDeletingPicture),
    loadUserProfile,
    changePassword,
    changeProfilePicture,
    deleteProfilePicture,
    clear
  }
})
