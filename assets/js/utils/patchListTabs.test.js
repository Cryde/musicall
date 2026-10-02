import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { directionToReveal, firstDirectionInError, patchErrorCount } from './patchListTabs.js'

/**
 * Which patch list tab an error belongs to (#1099).
 *
 * Run with `npm test`.
 */

const none = () => ({ list: [], rows: {} })

describe('patchErrorCount', () => {
  it('is zero without errors', () => {
    assert.equal(patchErrorCount(none()), 0)
  })

  it('counts a row once however many fields it has wrong', () => {
    assert.equal(
      patchErrorCount({ list: [], rows: { 0: ['Nom : trop long', 'Micro : trop long'] } }),
      1
    )
  })

  it('adds the list-level messages, such as a duplicate channel', () => {
    assert.equal(
      patchErrorCount({
        list: ['Le canal 3 est utilisé deux fois'],
        rows: { 2: ['Canal : requis'] }
      }),
      2
    )
  })

  it('ignores a row left with no message', () => {
    assert.equal(patchErrorCount({ list: [], rows: { 4: [] } }), 0)
  })
})

describe('firstDirectionInError', () => {
  it('is null when nothing is wrong', () => {
    assert.equal(firstDirectionInError({ inputs: none(), outputs: none() }), null)
  })

  it('points at the outputs when only they are wrong', () => {
    const outputs = { list: [], rows: { 0: ['Canal : requis'] } }

    assert.equal(firstDirectionInError({ inputs: none(), outputs }), 'outputs')
  })

  it('prefers the inputs, in tab order, when both are wrong', () => {
    const wrong = () => ({ list: ['Doublon'], rows: {} })

    assert.equal(firstDirectionInError({ inputs: wrong(), outputs: wrong() }), 'inputs')
  })
})

describe('directionToReveal', () => {
  const wrong = () => ({ list: [], rows: { 0: ['Canal : ce champ est requis'] } })

  it('moves to the tab in error when the open one is clean', () => {
    assert.equal(directionToReveal({ inputs: none(), outputs: wrong() }, 'inputs'), 'outputs')
  })

  it('stays on an open tab that has errors of its own', () => {
    assert.equal(directionToReveal({ inputs: wrong(), outputs: wrong() }, 'outputs'), null)
  })

  it('stays when nothing is wrong', () => {
    assert.equal(directionToReveal({ inputs: none(), outputs: none() }, 'outputs'), null)
  })
})
