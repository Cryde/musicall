import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import teacherProfileApi from '../../api/user/teacherProfile.js'

export const useTeacherProfileStore = defineStore('teacherProfile', () => {
  const profile = ref(null)
  const isLoading = ref(false)
  const isCreating = ref(false)
  const isUpdating = ref(false)
  const isDeleting = ref(false)

  async function loadPublicProfile(username) {
    isLoading.value = true
    try {
      profile.value = await teacherProfileApi.getPublicTeacherProfile(username)
      return profile.value
    } finally {
      isLoading.value = false
    }
  }

  async function loadMyProfile() {
    isLoading.value = true
    try {
      profile.value = await teacherProfileApi.getMyTeacherProfile()
      return profile.value
    } finally {
      isLoading.value = false
    }
  }

  async function createProfile(data) {
    isCreating.value = true
    try {
      profile.value = await teacherProfileApi.createTeacherProfile(data)
      return profile.value
    } finally {
      isCreating.value = false
    }
  }

  async function updateProfile(data) {
    isUpdating.value = true
    try {
      profile.value = await teacherProfileApi.updateTeacherProfile(data)
      return profile.value
    } finally {
      isUpdating.value = false
    }
  }

  async function deleteProfile() {
    isDeleting.value = true
    try {
      await teacherProfileApi.deleteTeacherProfile()
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
