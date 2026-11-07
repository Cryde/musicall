import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import profileApi from '../../api/user/profile.js'

export const useUserProfileStore = defineStore('userProfile', () => {
  const profile = ref(null)
  const socialLinks = ref([])
  const isLoading = ref(false)
  const isLoadingSocialLinks = ref(false)
  const isAddingSocialLink = ref(false)
  const isDeletingSocialLink = ref(false)
  const isUploadingCoverPicture = ref(false)
  const isDeletingCoverPicture = ref(false)

  /** The public profile page's member (#1317); the settings read the owner's own through the API. */
  async function loadProfile(username) {
    isLoading.value = true
    try {
      profile.value = await profileApi.getPublicProfile(username)
      return profile.value
    } finally {
      isLoading.value = false
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
      // No reload here: on the public profile page `profile` holds the public profile, which the edit shape would
      // replace. Each page reloads what it shows.
      await profileApi.uploadCoverPicture(file)
    } finally {
      isUploadingCoverPicture.value = false
    }
  }

  async function deleteCoverPicture() {
    isDeletingCoverPicture.value = true
    try {
      // No reload, as for the upload: the page reloads what it shows.
      await profileApi.deleteCoverPicture()
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
    isLoadingSocialLinks: readonly(isLoadingSocialLinks),
    isAddingSocialLink: readonly(isAddingSocialLink),
    isDeletingSocialLink: readonly(isDeletingSocialLink),
    isUploadingCoverPicture: readonly(isUploadingCoverPicture),
    isDeletingCoverPicture: readonly(isDeletingCoverPicture),
    loadProfile,
    loadSocialLinks,
    addSocialLink,
    deleteSocialLink,
    uploadCoverPicture,
    deleteCoverPicture,
    clear
  }
})
