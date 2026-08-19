import { defineStore } from 'pinia'
import { readonly, ref } from 'vue'
import adminForumApi from '../../api/admin/forum.js'

export const useAdminForumReportStore = defineStore('adminForumReport', () => {
  const pendingReports = ref([])
  const isLoading = ref(false)

  async function loadPendingReports() {
    isLoading.value = true
    try {
      pendingReports.value = await adminForumApi.getPendingReports()
    } catch (e) {
      console.error('Failed to load pending forum reports:', e)
    } finally {
      isLoading.value = false
    }
  }

  async function resolveReport(id) {
    await adminForumApi.resolveReport(id)
    pendingReports.value = pendingReports.value.filter((report) => report.id !== id)
  }

  return {
    pendingReports: readonly(pendingReports),
    isLoading: readonly(isLoading),
    loadPendingReports,
    resolveReport
  }
})
