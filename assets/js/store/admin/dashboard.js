import { defineStore } from 'pinia'
import { reactive, readonly, ref } from 'vue'
import adminDashboardApi from '../../api/admin/dashboard.js'

export const useAdminDashboardStore = defineStore('adminDashboard', () => {
  const generalMetrics = ref(null)
  const userMetrics = ref(null)
  const isLoadingGeneral = ref(false)
  const isLoadingUsers = ref(false)
  const generalError = ref(null)
  const usersError = ref(null)

  const timeSeries = reactive({})
  const contentOverview = ref(null)
  const isLoadingContentOverview = ref(false)
  const forumRecentActivity = ref(null)
  const isLoadingForumRecentActivity = ref(false)

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

  async function loadUserMetrics(from, to) {
    isLoadingUsers.value = true
    usersError.value = null
    try {
      userMetrics.value = await adminDashboardApi.getUserMetrics(from, to)
    } catch (e) {
      console.error('Failed to load user metrics:', e)
      usersError.value = 'Impossible de charger les métriques utilisateurs'
    } finally {
      isLoadingUsers.value = false
    }
  }

  async function loadTimeSeries(metric, from, to) {
    timeSeries[metric] = { data: null, isLoading: true, error: null }
    try {
      const result = await adminDashboardApi.getTimeSeries(metric, from, to)
      timeSeries[metric] = { data: result, isLoading: false, error: null }
    } catch (e) {
      console.error(`Failed to load time series for ${metric}:`, e)
      timeSeries[metric] = {
        data: null,
        isLoading: false,
        error: 'Impossible de charger les données'
      }
    }
  }

  async function loadContentOverview(from, to) {
    isLoadingContentOverview.value = true
    try {
      contentOverview.value = await adminDashboardApi.getContentOverview(from, to)
    } catch (e) {
      console.error('Failed to load content overview:', e)
      contentOverview.value = null
    } finally {
      isLoadingContentOverview.value = false
    }
  }

  async function loadForumRecentActivity() {
    isLoadingForumRecentActivity.value = true
    try {
      forumRecentActivity.value = await adminDashboardApi.getForumRecentActivity()
    } catch (e) {
      console.error('Failed to load forum recent activity:', e)
      forumRecentActivity.value = null
    } finally {
      isLoadingForumRecentActivity.value = false
    }
  }

  function clear() {
    generalMetrics.value = null
    userMetrics.value = null
    contentOverview.value = null
    forumRecentActivity.value = null
    generalError.value = null
    usersError.value = null
    for (const key of Object.keys(timeSeries)) {
      delete timeSeries[key]
    }
  }

  return {
    generalMetrics: readonly(generalMetrics),
    userMetrics: readonly(userMetrics),
    isLoadingGeneral: readonly(isLoadingGeneral),
    isLoadingUsers: readonly(isLoadingUsers),
    generalError: readonly(generalError),
    usersError: readonly(usersError),
    timeSeries,
    contentOverview: readonly(contentOverview),
    isLoadingContentOverview: readonly(isLoadingContentOverview),
    forumRecentActivity: readonly(forumRecentActivity),
    isLoadingForumRecentActivity: readonly(isLoadingForumRecentActivity),
    loadGeneralMetrics,
    loadUserMetrics,
    loadTimeSeries,
    loadContentOverview,
    loadForumRecentActivity,
    clear
  }
})
