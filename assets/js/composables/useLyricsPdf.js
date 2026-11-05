import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceLyricsApi from '../api/bandSpace/band-space-lyrics.js'
import { downloadBlob } from '../utils/downloadBlob.js'

/** A set of lyrics as a PDF download, with the chords, singers and transposition asked for. */
export function useLyricsPdf() {
  const { t } = useI18n()
  const toast = useToast()
  const isExporting = ref(false)

  /** @returns {Promise<boolean>} whether the file was handed over */
  async function exportPdf(bandSpaceId, lyricsId, options) {
    if (isExporting.value) return false
    isExporting.value = true
    try {
      const { blob, filename } = await bandSpaceLyricsApi.downloadPdf(
        bandSpaceId,
        lyricsId,
        options
      )
      downloadBlob(blob, filename ?? 'paroles.pdf')
      return true
    } catch (error) {
      // A 401 already sends the member to log in; the body of a 5xx says nothing usable.
      if (error?.response?.status !== 401) {
        toast.add({
          severity: 'error',
          summary: t('band_space_setlist.pdf_export.failed_summary'),
          detail: t('band_space_setlist.pdf_export.failed_detail'),
          life: 6000
        })
      }
      return false
    } finally {
      isExporting.value = false
    }
  }

  return { isExporting, exportPdf }
}
