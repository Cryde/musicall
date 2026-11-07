const HTTP_TOO_MANY_REQUESTS = 429

/** Too many renders in a short time (#1097): waiting is the fix, the PDF itself is fine. */
export const PDF_EXPORT_RATE_LIMITED = 'rate_limited'
/** Anything else. The body says nothing usable, API Platform blanks the message on a 5xx. */
export const PDF_EXPORT_UNAVAILABLE = 'unavailable'

/**
 * Why a PDF export failed, as far as the member can act on it. Shared by the tech rider preview and
 * the download buttons, which all go through axios with a blob response, so the status is all there is.
 */
export function pdfExportFailure(error) {
  return error?.response?.status === HTTP_TOO_MANY_REQUESTS
    ? PDF_EXPORT_RATE_LIMITED
    : PDF_EXPORT_UNAVAILABLE
}
