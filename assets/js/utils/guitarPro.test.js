import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { guitarProTitle, isGuitarProName } from './guitarPro.js'

describe('isGuitarProName', () => {
  it('recognises every Guitar Pro format the reader opens', () => {
    for (const name of ['New 45.gp', 'riff.gp3', 'riff.gp4', 'riff.gp5', 'riff.gpx']) {
      assert.equal(isGuitarProName(name), true, name)
    }
  })

  it('ignores the case of the extension', () => {
    assert.equal(isGuitarProName('7-NEW PRIO.GP'), true)
    assert.equal(isGuitarProName('solo.Gp5'), true)
  })

  it('reads only the last extension', () => {
    assert.equal(isGuitarProName('song.gp.zip'), false)
    assert.equal(isGuitarProName('song.zip.gp'), true)
  })

  it('refuses other files and names without an extension', () => {
    for (const name of ['Paroles.pdf', 'repete.m4a', 'gp', '.gp', 'notes.gpt', 'gp5', '']) {
      assert.equal(isGuitarProName(name), false, name)
    }
    assert.equal(isGuitarProName(null), false)
    assert.equal(isGuitarProName(undefined), false)
  })
})

describe('guitarProTitle', () => {
  it('drops the Guitar Pro extension, whatever its case', () => {
    assert.equal(guitarProTitle('New 45.gp'), 'New 45')
    assert.equal(guitarProTitle('7-NEW PRIO.GP5'), '7-NEW PRIO')
    assert.equal(guitarProTitle('v1.2 riff.gpx'), 'v1.2 riff')
  })

  it('keeps any other name as it is', () => {
    assert.equal(guitarProTitle('Paroles.pdf'), 'Paroles.pdf')
    assert.equal(guitarProTitle(null), '')
  })
})
