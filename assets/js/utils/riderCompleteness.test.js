import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { riderReadiness, sectionProblems } from './riderCompleteness.js'

/**
 * The « Prêt à envoyer » card (#1093). Run with `npm test`.
 */

const text = (overrides = {}) => ({
  id: 'text',
  type: 'text',
  title: 'Catering',
  is_included: true,
  is_empty: false,
  ...overrides
})

const patchList = (inputs, overrides = {}) => ({
  id: 'patch',
  type: 'patch_list',
  title: 'Patch list',
  is_included: true,
  is_empty: inputs.length === 0,
  patch_list: { inputs, outputs: [] },
  ...overrides
})

const input = (channel, name, microphone, stereo = false) => ({ channel, name, microphone, stereo })

describe('sectionProblems', () => {
  it('has nothing to say about a filled section', () => {
    assert.deepEqual(sectionProblems(text()), [])
  })

  it('has nothing to say about a patch list the API sent without its grid', () => {
    assert.deepEqual(sectionProblems({ ...patchList([]), patch_list: null }), [])
  })

  it('reads a microphone of spaces only as no microphone', () => {
    assert.deepEqual(sectionProblems(patchList([input(2, 'SNARE', '   ')])), [
      '1 entrée sans micro (canal 2)'
    ])
  })

  it('leaves out the channels when the row has none yet', () => {
    assert.deepEqual(sectionProblems(patchList([input(null, 'SNARE', null)])), [
      '1 entrée sans micro'
    ])
  })

  it('names the channel of an input with no microphone', () => {
    assert.deepEqual(
      sectionProblems(patchList([input(1, 'KICK', 'Beta 91A'), input(7, 'CHŒURS', null)])),
      ['1 entrée sans micro (canal 7)']
    )
  })

  it('counts inputs with no name and no microphone separately, stereo pairs as printed', () => {
    assert.deepEqual(
      sectionProblems(
        patchList([input(3, '  ', 'SM57'), input(8, null, '', true), input(10, 'CHANT', 'SM58')])
      ),
      ['2 entrées sans nom (canaux 3, 8-9)', '1 entrée sans micro (canal 8-9)']
    )
  })

  it('leaves the outputs alone: a wedge has no microphone', () => {
    const item = patchList([input(1, 'KICK', 'Beta 91A')])
    item.patch_list.outputs = [input(1, 'WEDGE', null)]

    assert.deepEqual(sectionProblems(item), [])
  })
})

describe('riderReadiness', () => {
  it('counts only the sections visible in the PDF', () => {
    const readiness = riderReadiness([
      text({ id: 'a' }),
      text({ id: 'b', is_empty: true, is_included: false }),
      patchList([input(7, 'CHŒURS', null)])
    ])

    assert.equal(readiness.total, 2)
    assert.equal(readiness.ready, 1)
    assert.deepEqual(readiness.empty, [])
    assert.deepEqual(readiness.problems, [
      { itemId: 'patch', title: 'Patch list', messages: ['1 entrée sans micro (canal 7)'] }
    ])
  })

  it('lists the empty sections apart from the other problems', () => {
    const readiness = riderReadiness([
      text({ id: 'a', title: 'Catering', is_empty: true }),
      text({ id: 'b', title: 'Éclairage', is_empty: true }),
      patchList([input(7, 'CHŒURS', null)]),
      text({ id: 'c' })
    ])

    assert.equal(readiness.ready, 1)
    assert.deepEqual(readiness.empty, [
      { itemId: 'a', title: 'Catering' },
      { itemId: 'b', title: 'Éclairage' }
    ])
    assert.deepEqual(
      readiness.problems.map((problem) => problem.itemId),
      ['patch']
    )
  })

  it('is ready when every visible section is', () => {
    assert.deepEqual(riderReadiness([text()]), { total: 1, ready: 1, empty: [], problems: [] })
  })

  it('has nothing to count on a rider with nothing visible', () => {
    assert.deepEqual(riderReadiness([text({ is_included: false })]), {
      total: 0,
      ready: 0,
      empty: [],
      problems: []
    })
  })
})
