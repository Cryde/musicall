import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { attachedFileAction, FILE_ACTIONS, featuredTab } from './attachedFileAction.js'

const tester = { canReadTabs: true }
const member = { canReadTabs: false }

describe('attachedFileAction', () => {
  it('opens a Guitar Pro file in the reader, told by its name whatever its stored type', () => {
    assert.equal(
      attachedFileAction({ original_name: 'New 45.gp', mime_type: 'application/zip' }, tester),
      FILE_ACTIONS.READ
    )
    assert.equal(
      attachedFileAction(
        { original_name: 'riff.GP5', mime_type: 'application/octet-stream' },
        tester
      ),
      FILE_ACTIONS.READ
    )
  })

  it('downloads a Guitar Pro file for a member who may not read tabs yet', () => {
    assert.equal(
      attachedFileAction({ original_name: 'New 45.gp', mime_type: 'application/zip' }, member),
      FILE_ACTIONS.DOWNLOAD
    )
  })

  it('plays a sound where it is', () => {
    assert.equal(
      attachedFileAction({ original_name: 'répète.m4a', mime_type: 'audio/mp4' }, member),
      FILE_ACTIONS.PLAY
    )
    assert.equal(
      attachedFileAction({ original_name: 'prise.mp3', mime_type: 'AUDIO/MPEG' }, member),
      FILE_ACTIONS.PLAY
    )
  })

  it('shows a PDF, an image or a video in the browser', () => {
    for (const mime of ['application/pdf', 'image/png', 'video/mp4']) {
      assert.equal(
        attachedFileAction({ original_name: 'x', mime_type: mime }, member),
        FILE_ACTIONS.VIEW,
        mime
      )
    }
  })

  it('downloads anything else, a file without a type included', () => {
    assert.equal(
      attachedFileAction({ original_name: 'grille.docx', mime_type: 'application/msword' }, tester),
      FILE_ACTIONS.DOWNLOAD
    )
    assert.equal(
      attachedFileAction({ original_name: 'notes', mime_type: null }, tester),
      FILE_ACTIONS.DOWNLOAD
    )
  })
})

describe('featuredTab', () => {
  it('picks the Guitar Pro file changed last', () => {
    const older = { id: 'a', original_name: 'v1.gp', update_datetime: '2026-11-01T10:00:00+00:00' }
    const newer = { id: 'b', original_name: 'v2.gp', update_datetime: '2026-11-19T10:00:00+00:00' }
    const audio = {
      id: 'c',
      original_name: 'demo.m4a',
      update_datetime: '2026-11-20T10:00:00+00:00'
    }

    assert.equal(featuredTab([older, audio, newer]), newer)
  })

  it('falls back on the creation date when the file was never changed', () => {
    const first = {
      id: 'a',
      original_name: 'a.gp',
      update_datetime: null,
      creation_datetime: '2026-10-01T10:00:00+00:00'
    }
    const second = {
      id: 'b',
      original_name: 'b.gpx',
      creation_datetime: '2026-10-05T10:00:00+00:00'
    }

    assert.equal(featuredTab([first, second]), second)
  })

  it('has nothing to feature without a Guitar Pro file', () => {
    assert.equal(featuredTab([{ id: 'c', original_name: 'demo.m4a' }]), null)
    assert.equal(featuredTab([]), null)
  })
})
