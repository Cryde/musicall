import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { firstUploadedTab, tabLastChange, tabNamed, tabSongTitles } from './tabList.js'

describe('tabLastChange', () => {
  it('is the update date once the file was changed', () => {
    const file = {
      creation_datetime: '2026-01-01T10:00:00+00:00',
      update_datetime: '2026-03-01T10:00:00+00:00'
    }

    assert.equal(tabLastChange(file), '2026-03-01T10:00:00+00:00')
  })

  it('is the upload date for a file never changed since', () => {
    const file = { creation_datetime: '2026-01-01T10:00:00+00:00', update_datetime: null }

    assert.equal(tabLastChange(file), '2026-01-01T10:00:00+00:00')
  })
})

describe('tabSongTitles', () => {
  it('names the songs only, not the other things the file is attached to', () => {
    const file = {
      attachments: [
        { source_type: 'song', source_label: 'Nouvelle bagarre' },
        { source_type: 'task', source_label: 'Relever le solo' },
        { source_type: 'song', source_label: 'Ballade' }
      ]
    }

    assert.deepEqual(tabSongTitles(file), ['Nouvelle bagarre', 'Ballade'])
  })

  it('is empty for a file attached to nothing', () => {
    assert.deepEqual(tabSongTitles({ attachments: [] }), [])
    assert.deepEqual(tabSongTitles({}), [])
  })
})

describe('tabNamed', () => {
  const tabs = [
    { id: 'a', original_name: 'Intro.gp' },
    { id: 'b', original_name: 'riff.gp5' }
  ]

  it('finds the tab carrying the picked name, whatever the case', () => {
    assert.equal(tabNamed(tabs, 'intro.GP')?.id, 'a')
  })

  it('wants the whole name, not a part of it', () => {
    // The server's search is a substring match, so its candidates include names that only contain it.
    assert.equal(tabNamed([{ id: 'c', original_name: 'riff.gp5.bak.gp5' }], 'riff.gp5'), null)
    assert.equal(tabNamed(tabs, 'riff.gp'), null)
  })

  it('is null when no tab has the name', () => {
    assert.equal(tabNamed(tabs, 'outro.gpx'), null)
  })
})

describe('firstUploadedTab', () => {
  it('is the first Guitar Pro file of the batch', () => {
    const files = [
      { id: 'pdf', original_name: 'rider.pdf' },
      { id: 'gp', original_name: 'song.gp' },
      { id: 'gp5', original_name: 'other.gp5' }
    ]

    assert.equal(firstUploadedTab(files)?.id, 'gp')
  })

  it('is null when the batch stored no Guitar Pro file', () => {
    assert.equal(firstUploadedTab([{ id: 'pdf', original_name: 'rider.pdf' }]), null)
    assert.equal(firstUploadedTab([]), null)
  })
})
