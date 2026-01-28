import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import adminDashboardApi from '../../api/admin/dashboard.js'

export const useAdminDashboardStore = defineStore('adminDashboard', () => {
  const generalMetrics = ref(null)
  const userMetrics = ref(null)
  const isLoadingGeneral = ref(false)
  const isLoadingUsers = ref(false)
  const generalError = ref(null)
  const usersError = ref(null)

  async function loadGeneralMetrics() {
    isLoadingGeneral.value = true
    generalError.value = null
    try {
      generalMetrics.value = await adminDashboardApi.getGeneralMetrics()
    } catch (e) {
      console.error('Failed to load general metrics:', e)
      generalError.value = 'Impossible de charger les métriques générales'
    } finally {
      isLoadingGeneral.value = false
    }
  }

  async function loadUserMetrics() {
    isLoadingUsers.value = true
    usersError.value = null
    try {
      userMetrics.value = await adminDashboardApi.getUserMetrics()
    } catch (e) {
      console.error('Failed to load user metrics:', e)
      usersError.value = 'Impossible de charger les métriques utilisateurs'
    } finally {
      isLoadingUsers.value = false
    }
  }

  function clear() {
    generalMetrics.value = null
    userMetrics.value = null
    generalError.value = null
    usersError.value = null
  }

  return {
    generalMetrics: readonly(generalMetrics),
    userMetrics: readonly(userMetrics),
    isLoadingGeneral: readonly(isLoadingGeneral),
    isLoadingUsers: readonly(isLoadingUsers),
    generalError: readonly(generalError),
    usersError: readonly(usersError),
    loadGeneralMetrics,
    loadUserMetrics,
    clear
  }
})
