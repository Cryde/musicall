import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'

/** The toasts that close an upload, shared by Files and Tablatures. */
export function useFileUploadToasts() {
  const toast = useToast()
  const { t } = useI18n()

  function warnQuotaApproaching() {
    toast.add({
      severity: 'warn',
      summary: t('band_space_files.common.quota_approaching.summary'),
      detail: t('band_space_files.common.quota_approaching.detail'),
      life: 6000
    })
  }

  /** What a FileUploadDialog batch stored, from its `saved` event. */
  function announceUpload({ uploadedCount, total, label, quotaApproaching }) {
    if (uploadedCount > 0) {
      toast.add({
        severity: 'success',
        summary: t('band_space_files.page.upload_toast.summary', uploadedCount),
        // One file keeps the sentence it has always been given: a bare count reads as a fragment when
        // there was never a batch to count. The tally is only worth quoting for a real batch.
        detail: total === 1 ? t('band_space_files.page.upload_toast.single_detail') : label,
        life: 3000
      })
    }
    if (quotaApproaching) {
      warnQuotaApproaching()
    }
  }

  /** A FileNewVersionDialog upload, from its `uploaded` event. */
  function announceVersion({ quotaApproaching }) {
    toast.add({
      severity: 'success',
      summary: t('band_space_files.version_panel.uploaded'),
      life: 3000
    })
    if (quotaApproaching) {
      warnQuotaApproaching()
    }
  }

  return { announceUpload, announceVersion }
}
