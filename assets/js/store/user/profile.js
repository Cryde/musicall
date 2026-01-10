import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import profileApi from '../../api/user/profile.js'

export const useUserProfileStore = defineStore('userProfile', () => {
  const profile = ref(null)
  const socialLinks = ref([])
  const isLoading = ref(false)
  const isUpdating = ref(false)
  const isLoadingSocialLinks = ref(false)
  const isAddingSocialLink = ref(false)
  const isDeletingSocialLink = ref(false)
  const isUploadingCoverPicture = ref(false)
  const isDeletingCoverPicture = ref(false)

  async function loadProfile(username = null) {
    isLoading.value = true
    try {
      if (username) {
        profile.value = await profileApi.getPublicProfile(username)
      } else {
        profile.value = await profileApi.getMyProfile()
      }
      return profile.value
    } finally {
      isLoading.value = false
    }
  }

  async function updateProfile(data) {
    isUpdating.value = true
    try {
      profile.value = await profileApi.updateMyProfile(data)
      return profile.value
    } finally {
      isUpdating.value = false
    }
  }

  async function loadSocialLinks() {
    isLoadingSocialLinks.value = true
    try {
      socialLinks.value = await profileApi.getMySocialLinks()
      return socialLinks.value
    } finally {
      isLoadingSocialLinks.value = false
    }
  }

  async function addSocialLink(data) {
    isAddingSocialLink.value = true
    try {
      const newLink = await profileApi.addSocialLink(data)
      socialLinks.value.push(newLink)
      return newLink
    } finally {
      isAddingSocialLink.value = false
    }
  }

  async function deleteSocialLink(id) {
    isDeletingSocialLink.value = true
    try {
      await profileApi.deleteSocialLink(id)
      socialLinks.value = socialLinks.value.filter((link) => link.id !== id)
    } finally {
      isDeletingSocialLink.value = false
    }
  }

  async function uploadCoverPicture(file) {
    isUploadingCoverPicture.value = true
    try {
      await profileApi.uploadCoverPicture(file)
      // Reload profile to get the new cover picture URL
      await loadProfile()
    } finally {
      isUploadingCoverPicture.value = false
    }
  }

  async function deleteCoverPicture() {
    isDeletingCoverPicture.value = true
    try {
      await profileApi.deleteCoverPicture()
      // Reload profile to reflect the deletion
      await loadProfile()
    } finally {
      isDeletingCoverPicture.value = false
    }
  }

  function clear() {
    profile.value = null
    socialLinks.value = []
  }

  return {
    profile: readonly(profile),
    socialLinks: readonly(socialLinks),
    isLoading: readonly(isLoading),
    isUpdating: readonly(isUpdating),
    isLoadingSocialLinks: readonly(isLoadingSocialLinks),
    isAddingSocialLink: readonly(isAddingSocialLink),
    isDeletingSocialLink: readonly(isDeletingSocialLink),
    isUploadingCoverPicture: readonly(isUploadingCoverPicture),
    isDeletingCoverPicture: readonly(isDeletingCoverPicture),
    loadProfile,
    updateProfile,
    loadSocialLinks,
    addSocialLink,
    deleteSocialLink,
    uploadCoverPicture,
    deleteCoverPicture,
    clear
  }
})
