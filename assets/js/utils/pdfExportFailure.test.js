import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  PDF_EXPORT_RATE_LIMITED,
  PDF_EXPORT_UNAVAILABLE,
  pdfExportFailure
} from './pdfExportFailure.js'

/**
 * A PDF export refused by the rate limiter (#1097) reads as a pause, not as a broken renderer.
 *
 * Run with `npm test`.
 */

function axiosError(status) {
  return Object.assign(new Error(`Request failed with status code ${status}`), {
    response: { status, data: new Blob() }
  })
}

describe('pdfExportFailure', () => {
  it('maps a 429 to the rate limited state', () => {
    assert.equal(pdfExportFailure(axiosError(429)), PDF_EXPORT_RATE_LIMITED)
  })

  it('maps a failing renderer to the unavailable state', () => {
    assert.equal(pdfExportFailure(axiosError(502)), PDF_EXPORT_UNAVAILABLE)
    assert.equal(pdfExportFailure(axiosError(500)), PDF_EXPORT_UNAVAILABLE)
  })

  it('maps a refusal other than the limiter to the unavailable state', () => {
    assert.equal(pdfExportFailure(axiosError(403)), PDF_EXPORT_UNAVAILABLE)
    assert.equal(pdfExportFailure(axiosError(404)), PDF_EXPORT_UNAVAILABLE)
  })

  it('maps an error with no response, a network failure, to the unavailable state', () => {
    assert.equal(pdfExportFailure(new Error('Network Error')), PDF_EXPORT_UNAVAILABLE)
    assert.equal(pdfExportFailure(undefined), PDF_EXPORT_UNAVAILABLE)
  })
})
