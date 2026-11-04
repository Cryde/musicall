import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { fileIconClass, fileTypeLabel } from './fileType.js'

describe('fileTypeLabel', () => {
  it('names a document by what people call it', () => {
    assert.equal(fileTypeLabel('application/pdf'), 'PDF')
    assert.equal(
      fileTypeLabel('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
      'Word'
    )
    assert.equal(fileTypeLabel('text/plain; charset=utf-8'), 'TXT')
  })

  it('puts the kind before the extension for a media file', () => {
    assert.equal(fileTypeLabel('image/jpeg'), 'Image JPEG')
    assert.equal(fileTypeLabel('audio/mpeg'), 'Audio MP3')
    assert.equal(fileTypeLabel('audio/x-wav'), 'Audio WAV')
    assert.equal(fileTypeLabel('video/mp4'), 'Vidéo MP4')
    assert.equal(fileTypeLabel('image/svg+xml'), 'Image SVG')
  })

  it('keeps the kind alone when the subtype says nothing readable', () => {
    assert.equal(fileTypeLabel('video/vnd.dlna.mpeg-tts'), 'Vidéo')
  })

  it('uppercases a short unknown subtype and keeps a long one as its MIME', () => {
    assert.equal(fileTypeLabel('application/x-gzip'), 'GZIP')
    assert.equal(
      fileTypeLabel('application/vnd.oasis.opendocument.text'),
      'application/vnd.oasis.opendocument.text'
    )
  })

  it('reads no type as no label', () => {
    assert.equal(fileTypeLabel(null), null)
    assert.equal(fileTypeLabel(''), null)
  })
})

describe('fileIconClass', () => {
  it('draws the icon of the kind', () => {
    assert.equal(fileIconClass('audio/mpeg'), 'pi pi-volume-up')
    assert.equal(fileIconClass('image/png'), 'pi pi-image')
    assert.equal(fileIconClass('video/mp4'), 'pi pi-video')
    assert.equal(fileIconClass('application/pdf'), 'pi pi-file-pdf')
    assert.equal(fileIconClass('application/zip'), 'pi pi-file')
    assert.equal(fileIconClass(null), 'pi pi-file')
  })
})
