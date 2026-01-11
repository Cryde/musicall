import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import musicianProfileApi from '../../api/user/musicianProfile.js'

export const useMusicianProfileStore = defineStore('musicianProfile', () => {
  const profile = ref(null)
  const isLoading = ref(false)
  const isCreating = ref(false)
  const isUpdating = ref(false)
  const isDeleting = ref(false)

  async function loadPublicProfile(username) {
    isLoading.value = true
    try {
      profile.value = await musicianProfileApi.getPublicMusicianProfile(username)
      return profile.value
    } finally {
      isLoading.value = false
    }
  }

  async function loadMyProfile() {
    isLoading.value = true
    try {
      profile.value = await musicianProfileApi.getMyMusicianProfile()
      return profile.value
    } finally {
      isLoading.value = false
    }
  }

  async function createProfile(data) {
    isCreating.value = true
    try {
      profile.value = await musicianProfileApi.createMusicianProfile(data)
      return profile.value
    } finally {
      isCreating.value = false
    }
  }

  async function updateProfile(data) {
    isUpdating.value = true
    try {
      profile.value = await musicianProfileApi.updateMusicianProfile(data)
      return profile.value
    } finally {
      isUpdating.value = false
    }
  }

  async function deleteProfile() {
    isDeleting.value = true
    try {
      await musicianProfileApi.deleteMusicianProfile()
      profile.value = null
    } finally {
      isDeleting.value = false
    }
  }

  function clear() {
    profile.value = null
  }

  return {
    profile: readonly(profile),
    isLoading: readonly(isLoading),
    isCreating: readonly(isCreating),
    isUpdating: readonly(isUpdating),
    isDeleting: readonly(isDeleting),
    loadPublicProfile,
    loadMyProfile,
    createProfile,
    updateProfile,
    deleteProfile,
    clear
  }
})
