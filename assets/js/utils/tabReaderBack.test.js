import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { tabReaderBackLabel, tabReaderRoute } from './tabReaderBack.js'

const t = (key) => `t:${key}`

describe('tabReaderBackLabel', () => {
  it('says what the page that opened the tab named itself, a song by its title', () => {
    assert.equal(
      tabReaderBackLabel({ backLabel: 'New 45', previousRouteName: 'app_band_song' }, t),
      'New 45'
    )
  })

  it('says Tablatures when going back to the Tablatures page', () => {
    assert.equal(
      tabReaderBackLabel({ previousRouteName: 'app_band_tabs' }, t),
      't:band_space_tabs.head.back_to_tabs_label'
    )
  })

  it('says Fichiers when going back to Files, or when there is nothing to go back to', () => {
    assert.equal(
      tabReaderBackLabel({ previousRouteName: 'app_band_files' }, t),
      't:band_space_tabs.head.files'
    )
    assert.equal(tabReaderBackLabel({ previousRouteName: null }, t), 't:band_space_tabs.head.files')
  })

  it('ignores a blank or foreign label', () => {
    assert.equal(
      tabReaderBackLabel({ backLabel: '  ', previousRouteName: null }, t),
      't:band_space_tabs.head.files'
    )
    assert.equal(
      tabReaderBackLabel({ backLabel: { x: 1 }, previousRouteName: null }, t),
      't:band_space_tabs.head.files'
    )
  })
})

describe('tabReaderRoute', () => {
  it('opens the reader on the file, carrying the name of the way back', () => {
    assert.deepEqual(tabReaderRoute('space-1', 'file-2', 'New 45'), {
      name: 'app_band_tab_reader',
      params: { id: 'space-1', fileId: 'file-2' },
      state: { backLabel: 'New 45' }
    })
  })

  it('carries an empty label when the caller names nothing, which the label falls back from', () => {
    assert.deepEqual(tabReaderRoute('space-1', 'file-2').state, { backLabel: '' })
  })
})
