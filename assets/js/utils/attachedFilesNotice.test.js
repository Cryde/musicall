import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { attachedFilesNotice } from './attachedFilesNotice.js'

/**
 * The warning appended to a delete confirmation when the source being deleted holds files.
 *
 * Run with `npm test`.
 */

describe('attachedFilesNotice', () => {
  it('says nothing when the source holds no file', () => {
    assert.equal(attachedFilesNotice(0), '')
  })

  it('says nothing when the count is unknown', () => {
    assert.equal(attachedFilesNotice(null), '')
    assert.equal(attachedFilesNotice(undefined), '')
  })

  it('stays singular for a single file', () => {
    assert.equal(
      attachedFilesNotice(1),
      ' Le fichier attaché sera détaché mais restera dans Fichiers.'
    )
  })

  it('counts and pluralises beyond one file', () => {
    assert.equal(
      attachedFilesNotice(3),
      ' Les 3 fichiers attachés seront détachés mais resteront dans Fichiers.'
    )
  })
})
