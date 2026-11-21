import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { titleFromFirstFile } from './newSongTitle.js'

describe('titleFromFirstFile', () => {
  it('names the song after the first file', () => {
    assert.equal(titleFromFirstFile([{ name: 'New 46.gp' }, { name: 'idée.m4a' }]), 'New 46')
  })

  it('keeps every dot but the extension', () => {
    assert.equal(titleFromFirstFile([{ name: 'v2.1 refrain.mp3' }]), 'v2.1 refrain')
  })

  it('keeps a name without an extension, or starting with a dot, whole', () => {
    assert.equal(titleFromFirstFile([{ name: 'riff' }]), 'riff')
    assert.equal(titleFromFirstFile([{ name: '.hidden' }]), '.hidden')
  })

  it('has no title without a file', () => {
    assert.equal(titleFromFirstFile([]), '')
  })
})
